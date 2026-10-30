<?php

namespace App\Http\Controllers;

use App\Constants\PresensiMessages;
use App\Exceptions\PdfTooLargeException;
use App\Exports\LaporanPresensiExport;
use App\Helpers\NotificationHelper;
use App\Helpers\PayrollLockHelper;
use App\Http\Controllers\Concerns\ScopesPimpinan;
use App\Http\Requests\PresensiPersonalReportRequest;
use App\Jobs\ProcessPresensiFoto;
use App\Models\AbsenSiswaSesi;
use App\Models\AuditPresensi;
use App\Models\HariLibur;
use App\Models\Jadwal;
use App\Models\Pegawai;
use App\Models\PengajuanIzin;
use App\Models\PiketSiswaTerlambat;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Notifications\StatusLembur;
use App\Services\AbsenSiswaService;
use App\Services\GeocodingService;
use App\Services\ImageUploadService;
use App\Services\KeuanganClient;
use App\Services\LaporanPdfService;
use App\Services\PresensiPersonalReportService;
use App\Traits\CalculatesDistance;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class PresensiController extends Controller
{
    use CalculatesDistance;
    use ScopesPimpinan;

    public function index(Request $request)
    {
        $request->validate([
            'lokasi_filter' => 'nullable|in:perlu_review,pulang_awal,review_semua',
            'suspicious_filter' => 'nullable|boolean',
            'status_filter' => 'nullable|in:hadir,telat,sakit,izin,cuti,alpa,belum_presensi',
            'jadwal_filter' => 'nullable|in:sedang_berlangsung',
            'jenis_filter' => 'nullable|in:pendidik,kependidikan',
            'search' => 'nullable|string|max:100',
            'sort' => 'nullable|in:nama,tanggal,masuk,keluar,status',
            'dir' => 'nullable|in:asc,desc',
        ]);

        $user = auth()->user();
        $isAdmin = $user && $user->can('view_presensi');
        $isPimpinan = $this->isPimpinanReadOnly($user);
        $isPersonal = ! $isAdmin || ($isPimpinan && $request->view_mode === 'self');
        $pegawai = null;
        $query = Presensi::with(['unitSekolah', 'unitLokasiMasuk:id,nama,unit_sekolah_id', 'unitLokasiKeluar:id,nama,unit_sekolah_id', 'pegawai.units:id,nama', 'pegawai.lokasis:id,nama,unit_sekolah_id', 'pegawai.jabatans:id,nama', 'jadwal.pegawaiMapel']);

        if ($isPersonal) {
            $pegawai = Pegawai::where('user_id', auth()->id())->first();
            $query->where('pegawai_id', $pegawai?->id ?? -1);
        } elseif ($isPimpinan) {
            if ($this->isKepsek($user)) {
                $unitId = $user->unit_sekolah_id
                    ?? $user->pegawai?->units->first()?->id;
                if ($unitId) {
                    $query->where('unit_sekolah_id', $unitId);
                } else {
                    $query->whereRaw('1 = 0');
                }
            } else {
                // Pimpinan lain (kepala TU/ketua yayasan): HANYA bawahan langsung.
                $this->scopePimpinanBawahan($query, $user);
            }
        } elseif ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
            $query->whereHas('pegawai', function ($q) use ($user) {
                $q->forUnit($user->unit_sekolah_id);
            });
        }

        // Default: filter ke hari ini jika tidak ada date filter dari request
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        if (! $startDate && ! $endDate) {
            $startDate = Carbon::today()->toDateString();
            $endDate = Carbon::today()->toDateString();
        }

        if ($startDate) {
            $query->where('tanggal', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('tanggal', '<=', $endDate);
        }

        if ($request->unit_id && $user->can('view_all_units')) {
            $query->whereHas('pegawai', function ($q) use ($request) {
                $q->whereHas('units', fn ($q2) => $q2->where('unit_sekolah.id', $request->unit_id));
            });
        }

        if ($request->search) {
            $query->whereHas('pegawai', function ($q) use ($request) {
                $q->where('nama_lengkap', 'like', '%'.$request->search.'%');
            });
        }

        // Ringkasan periode (scope + unit + search + tanggal) — bebas dari filter detail
        $stats = $this->presensiStats((clone $query), $isPersonal);

        // Filter lembur
        if ($request->lembur_filter === 'lembur_pending') {
            $query->where('is_lembur', true)->where('lembur_status', 'pending');
        } elseif ($request->lembur_filter === 'lembur_disetujui') {
            $query->where('is_lembur', true)->where('lembur_status', 'disetujui');
        } elseif ($request->lembur_filter === 'lembur_ditolak') {
            $query->where('is_lembur', true)->where('lembur_status', 'ditolak');
        } elseif ($request->lembur_filter === 'lembur_semua') {
            $query->where('is_lembur', true);
        }

        if ($request->lokasi_filter === 'perlu_review') {
            $query->where('lokasi_perlu_review', true);
        } elseif ($request->lokasi_filter === 'pulang_awal') {
            $query->where('is_pulang_awal', true);
        } elseif ($request->lokasi_filter === 'review_semua') {
            // Gabungan: perlu review ATAU posisi mencurigakan (anti-spoof v2)
            $query->where(function ($q) {
                $q->where('lokasi_perlu_review', true)
                    ->orWhere('posisi_mencurigakan', true);
            });
        }

        if ($request->suspicious_filter) {
            $query->where('posisi_mencurigakan', true);
        }

        // ── Special: "Belum Presensi" ──
        // Pegawai aktif di scope yang TIDAK punya record presensi di rentang tanggal.
        // Dipisah karena basis query-nya berbeda (pegawai LEFT JOIN presensi, bukan sebaliknya).
        if ($request->status_filter === 'belum_presensi') {
            return $this->handleBelumPresensi($request, $user, $stats, $isPersonal, $isPimpinan);
        }

        if ($request->status_filter) {
            $query->where('status', $request->status_filter);
        }

        // Filter jenis pegawai (Dapodik-style): pendidik = punya jabatan guru (is_guru),
        // tenaga kependidikan = TIDAK punya jabatan guru sama sekali (TU, pustakawan, OB, dll).
        if ($request->jenis_filter === 'pendidik') {
            $query->whereHas('pegawai', fn ($q) => $q->whereHas('jabatans', fn ($q2) => $q2->where('is_guru', true)));
        } elseif ($request->jenis_filter === 'kependidikan') {
            $query->whereHas('pegawai', fn ($q) => $q->whereDoesntHave('jabatans', fn ($q2) => $q2->where('is_guru', true)));
        }

        // Kelas yang sedang berlangsung SEKARANG: record mengajar hari ini yang
        // sudah absen masuk dan jam jadwal-nya sedang berjalan (jam_mulai <= now < jam_selesai).
        // Konsisten dengan badge "Mengajar" di daftar (Index.jsx).
        // Perbandingan string polos (bukan whereTime) supaya index komposit
        // idx_jadwal_pegawai_hari_jam tetap terpakai — jam selalu tersimpan H:i:s zero-padded.
        if ($request->jadwal_filter === 'sedang_berlangsung') {
            $now = Carbon::now()->format('H:i:s');
            $query->where('tanggal', Carbon::today()->toDateString())
                ->whereNotNull('jadwal_id')
                ->whereNotNull('jam_masuk')
                ->whereHas('jadwal', function ($q) use ($now) {
                    $q->where('jam_mulai', '<=', $now)
                        ->where('jam_selesai', '>', $now);
                });
        }

        $perPage = min(max((int) $request->input('per_page', 100), 1), 100);
        // Sort: tanggal DESC, lalu jam_masuk ASC untuk kronologis harian,
        // lalu id ASC tie-breaker supaya baris jam_masuk sama (NULL/pulang tanpa masuk) deterministik.
        // Hindari join jadwal (jam_mulai) — tambah N+1 + lockForUpdate tidak relevan di index read-only.
        if ($request->input('display_mode') === 'ringkas') {
            // View ringkas mengelompokkan baris per pegawai+tanggal (buildGroups di Index.jsx).
            // Pagination dihitung per KELOMPOK, bukan per baris, supaya ringkasan satu orang
            // tidak terbelah antar halaman. `per_page` di mode ini = jumlah kelompok.
            $perPageGrup = min(max((int) $request->input('per_page', 25), 1), 100);
            $pageGrup = max((int) $request->input('page', 1), 1);

            $totalGrup = DB::query()->fromSub(
                (clone $query)->select('pegawai_id', 'tanggal')->groupBy('pegawai_id', 'tanggal'),
                'grup'
            )->count();

            // Urutan kelompok: NAMA pegawai ASC, baru kronologis. Dulu kuncinya jam masuk
            // terawal sehingga halaman awal hanya berisi yang absen pagi — yang telat selalu
            // terlempar ke halaman belakang. Join di-alias `p` supaya kolom presensi
            // (status, tanggal, id, ...) tetap tidak ambigu.
            $grupHalaman = (clone $query)
                ->join('pegawai as p', 'p.id', '=', 'presensi.pegawai_id')
                ->select('presensi.pegawai_id', 'presensi.tanggal', 'p.nama_lengkap')
                ->selectRaw('MIN(presensi.jam_masuk) as jam_pertama')
                ->selectRaw('MIN(presensi.id) as id_pertama')
                ->groupBy('presensi.pegawai_id', 'presensi.tanggal', 'p.nama_lengkap')
                ->orderBy('p.nama_lengkap', 'asc')
                ->orderBy('presensi.pegawai_id', 'asc')
                ->orderBy('presensi.tanggal', 'desc')
                ->orderBy('jam_pertama', 'asc')
                ->orderBy('id_pertama', 'asc')
                ->forPage($pageGrup, $perPageGrup)
                ->get();

            $pasangan = $grupHalaman->map(fn ($grup) => [
                $grup->pegawai_id,
                $grup->tanggal instanceof \DateTimeInterface ? $grup->tanggal->format('Y-m-d') : $grup->tanggal,
            ])->all();

            // Ambil semua baris milik kelompok di halaman ini. Pakai OR-group (portabel di
            // MySQL & SQLite) — jumlahnya dibatasi perPageGrup (maks 100) sehingga tetap murah.
            // whereDate dipakai agar cocok baik kolom tanggal menyimpan 'Y-m-d' maupun 'Y-m-d H:i:s'.
            $rows = (clone $query)->where(function ($q) use ($pasangan) {
                foreach ($pasangan as [$pegawaiId, $tanggal]) {
                    $q->orWhere(fn ($sub) => $sub->where('pegawai_id', $pegawaiId)->whereDate('tanggal', $tanggal));
                }
            });

            if ($pasangan === []) {
                $rows->whereRaw('1 = 0');
            }

            $presensis = $rows
                ->select('presensi.*')
                ->join('pegawai as p', 'p.id', '=', 'presensi.pegawai_id')
                ->orderBy('p.nama_lengkap', 'asc')
                ->orderBy('presensi.tanggal', 'desc')
                ->orderBy('presensi.jam_masuk', 'asc')
                ->orderBy('presensi.id', 'asc')
                ->get();
            $presensis->load('pegawai.jabatans');

            // Mode Ringkas hanya merender baris presensi, jadi JP terjadwal yang
            // tidak pernah diabsen perlu disisipkan agar atasan melihatnya.
            $presensis = $this->mergeJpTerlewat($presensis, $request, $startDate, $endDate);
            $presensis = new LengthAwarePaginator(
                $presensis, $totalGrup, $perPageGrup, $pageGrup,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        } else {
            // Sort kolom dikerjakan di SERVER supaya urutan konsisten lintas halaman
            // (dulu FE menyortir hanya halaman aktif). Default nama ASC = tampilan lama.
            $sort = $request->input('sort', 'nama');
            $dir = $request->input('dir', 'asc') === 'desc' ? 'desc' : 'asc';

            if ($sort === 'nama') {
                // nama_lengkap ada di tabel pegawai: butuh join (alias `p`) + select
                // eksplisit supaya `*` hasil join tidak menimpa kolom presensi (id).
                $query->select('presensi.*')
                    ->join('pegawai as p', 'p.id', '=', 'presensi.pegawai_id')
                    ->orderBy('p.nama_lengkap', $dir)
                    ->orderBy('presensi.tanggal', 'desc')
                    ->orderBy('presensi.jam_masuk', 'asc');
            } else {
                $kolom = [
                    'tanggal' => 'presensi.tanggal',
                    'masuk' => 'presensi.jam_masuk',
                    'keluar' => 'presensi.jam_keluar',
                    'status' => 'presensi.status',
                ][$sort];

                $query->orderBy($kolom, $dir);
            }

            $presensis = $query
                ->orderBy('presensi.id', 'asc')
                ->paginate($perPage)
                ->withQueryString();
            $presensis->load('pegawai.jabatans');
        }

        // Deep link di luar rentang (mis. ?page=99) → kosong tanpa penjelasan.
        // Alihkan ke halaman terakhir supaya data tetap terlihat.
        if ($presensis->total() > 0 && $presensis->currentPage() > $presensis->lastPage()) {
            return redirect()->to($presensis->url($presensis->lastPage()));
        }

        $units = [];
        if ($user->can('view_all_units')) {
            $units = UnitSekolah::orderBy('nama')->get();
        }

        return inertia('Presensi/Index', [
            'presensis' => $presensis,
            'pegawai' => $isPersonal ? $pegawai : null,
            'filters' => $request->only(['start_date', 'end_date', 'unit_id', 'lembur_filter', 'lokasi_filter', 'suspicious_filter', 'status_filter', 'jadwal_filter', 'jenis_filter', 'search', 'view_mode', 'per_page']),
            'units' => $units,
            'userRole' => $user->roles->first()?->name ?? 'pegawai',
            'stats' => $stats,
            'is_personal' => $isPersonal,
            'is_pimpinan' => $isPimpinan,
        ]);
    }

    /**
     * Menu lembur: daftar pengajuan lembur terpisah dari tabel presensi utama.
     * Default rentang = kemarin. Aksi approve/reject tetap via
     * presensi.approveLembur / rejectLembur; modal detail pakai presensi.review.
     */
    public function lemburIndex(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'lembur_status' => 'nullable|in:pending,disetujui,ditolak',
            'unit_id' => 'nullable|integer',
            'search' => 'nullable|string|max:100',
        ]);

        $user = auth()->user();
        $query = Presensi::with(['unitSekolah:id,nama', 'pegawai:id,nama_lengkap'])
            ->where('is_lembur', true);

        if ($user->unit_sekolah_id && ! $user->can('view_all_units')) {
            $query->whereHas('pegawai', fn ($q) => $q->forUnit($user->unit_sekolah_id));
        }

        // Default: kemarin saja, biar admin langsung lihat pengajuan semalam.
        $startDate = $request->start_date ?: Carbon::yesterday()->toDateString();
        $endDate = $request->end_date ?: $startDate;

        $query->whereDate('tanggal', '>=', $startDate)
            ->whereDate('tanggal', '<=', $endDate);

        // Count chip dihitung sebelum filter status/unit/search (scope + tanggal saja).
        $counts = (clone $query)->toBase()
            ->selectRaw("COALESCE(lembur_status, 'pending') as status_lembur, COUNT(*) as total")
            ->groupBy('status_lembur')
            ->pluck('total', 'status_lembur');

        if ($request->lembur_status) {
            $query->where('lembur_status', $request->lembur_status);
        }

        if ($request->unit_id && $user->can('view_all_units')) {
            $query->whereHas('pegawai', fn ($q) => $q->whereHas(
                'units',
                fn ($u) => $u->where('unit_sekolah.id', $request->unit_id)
            ));
        }

        if ($request->search) {
            $query->whereHas('pegawai', fn ($q) => $q->where('nama_lengkap', 'like', '%'.$request->search.'%'));
        }

        $lemburs = $query
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $units = [];
        if ($user->can('view_all_units')) {
            $units = UnitSekolah::orderBy('nama')->get(['id', 'nama']);
        }

        return inertia('Lembur/Index', [
            'lemburs' => $lemburs,
            'counts' => $counts,
            'filters' => $request->only(['start_date', 'end_date', 'lembur_status', 'unit_id', 'search']),
            'units' => $units,
        ]);
    }

    /**
     * Rekap absen siswa per kelas (harian). Sesi tersimpan + daftar kelas
     * yang belum diabsen (dari aplikasi keuangan). Integrasi gagal = rekap
     * tetap tampil tanpa daftar belum-diabsen.
     */
    public function absenSiswaRekap(Request $request)
    {
        $request->validate([
            'tanggal' => 'nullable|date',
            'unit_id' => 'nullable|integer',
        ]);

        $user = auth()->user();
        $tanggal = $request->tanggal ?: Carbon::today()->toDateString();

        $unitQuery = UnitSekolah::query();
        if ($user->unit_sekolah_id && ! $user->can('view_all_units')) {
            $unitQuery->whereKey($user->unit_sekolah_id);
        } elseif ($request->unit_id) {
            $unitQuery->whereKey($request->unit_id);
        }
        $unitList = $unitQuery->orderBy('nama')->get(['id', 'nama']);

        $rows = AbsenSiswaSesi::with(['pegawai:id,nama_lengkap', 'unitSekolah:id,nama', 'details', 'revisi.user:id,name'])
            ->whereDate('tanggal', $tanggal)
            ->whereIn('unit_sekolah_id', $unitList->pluck('id'))
            ->orderBy('class_label')
            ->get();

        $diabsen = $rows->groupBy('unit_sekolah_id')
            ->map(fn ($grup) => $grup->pluck('class_id')->map(fn ($id) => (string) $id)->all());

        $sesi = $rows->map(function (AbsenSiswaSesi $s) {
            $counts = $s->details->countBy('status');

            return [
                'id' => $s->id,
                'class_id' => $s->class_id,
                'class_label' => $s->class_label,
                'unit' => $s->unitSekolah?->nama,
                'pegawai' => $s->pegawai?->nama_lengkap,
                'done_at' => $s->done_at?->format('H:i'),
                'counts' => [
                    'hadir' => $counts->get('hadir', 0),
                    'telat' => $counts->get('telat', 0),
                    'izin' => $counts->get('izin', 0),
                    'sakit' => $counts->get('sakit', 0),
                    'alpa' => $counts->get('alpa', 0),
                ],
                'total' => $s->details->count(),
                'non_hadir' => $s->details
                    ->where('status', '!=', 'hadir')
                    ->map(fn ($d) => [
                        'nama' => $d->nama_snapshot,
                        'nis' => $d->nis,
                        'status' => $d->status,
                        'keterangan' => $d->keterangan,
                    ])
                    ->values(),
                'students' => $s->details
                    ->sortBy('nama_snapshot')
                    ->map(fn ($d) => [
                        'nama' => $d->nama_snapshot,
                        'nis' => $d->nis,
                        'status' => $d->status,
                        'keterangan' => $d->keterangan,
                    ])
                    ->values(),
            ];
        });

        // ponytail: satu panggilan keuangan per unit, sequential. Pindah ke
        // Http::pool bila jumlah unit besar dan halaman terasa lambat.
        $belum = collect();
        $integrasiOk = true;
        foreach ($unitList as $unit) {
            $kelas = KeuanganClient::kelasByUnit($unit->nama ?: $unit->singkatan);
            if ($kelas === null) {
                $integrasiOk = false;

                continue;
            }

            $sudah = $diabsen->get($unit->id, []);
            foreach ($kelas as $c) {
                if (in_array((string) ($c['id'] ?? ''), $sudah, true)) {
                    continue;
                }
                $label = trim(implode(' ', array_filter([$c['grade'] ?? null, $c['name'] ?? null, $c['section'] ?? null])));
                $belum->push([
                    'class_id' => (string) ($c['id'] ?? ''),
                    'class_label' => $label !== '' ? $label : '-',
                    'unit' => $unit->nama,
                ]);
            }
        }

        $units = $user->can('view_all_units')
            ? UnitSekolah::orderBy('nama')->get(['id', 'nama'])
            : [];

        $terlambatGerbang = PiketSiswaTerlambat::where('tanggal', $tanggal)
            ->whereIn('unit_sekolah_id', $unitList->pluck('id'))
            ->count();

        return inertia('AbsenSiswa/Index', [
            'sesi' => $sesi,
            'belum_diabsen' => $belum->sortBy('class_label')->values(),
            'integrasi_ok' => $integrasiOk,
            'terlambat_gerbang' => $terlambatGerbang,
            'filters' => ['tanggal' => $tanggal, 'unit_id' => $request->unit_id],
            'units' => $units,
        ]);
    }

    public function koreksiAbsenSiswa(Request $request, AbsenSiswaSesi $sesi)
    {
        $user = $request->user();
        abort_unless($user && $user->can('view_presensi'), 403);
        abort_if($user->unit_sekolah_id && ! $user->can('view_all_units') && (int) $user->unit_sekolah_id !== (int) $sesi->unit_sekolah_id, 403);

        $validated = $request->validate([
            'alasan' => 'required|string|min:5|max:255',
            'siswa' => 'required|array|min:1|max:200',
            'siswa.*.nis' => 'required|string|max:30|distinct',
            'siswa.*.nama' => 'required|string|max:150',
            'siswa.*.status' => 'required|in:hadir,telat,izin,sakit,alpa',
            'siswa.*.keterangan' => 'nullable|string|max:255',
        ]);

        foreach ($validated['siswa'] as $i => $row) {
            if (in_array($row['status'], ['izin', 'sakit'], true) && trim((string) ($row['keterangan'] ?? '')) === '') {
                throw ValidationException::withMessages([
                    "siswa.$i.keterangan" => 'Keterangan wajib untuk siswa izin/sakit.',
                ]);
            }
        }

        AbsenSiswaService::simpan($sesi, $validated['siswa'], $user->id, 'admin', $validated['alasan']);

        return back()->with('message', 'Absen siswa dikoreksi dan riwayat tersimpan.');
    }

    /**
     * Ringkasan presensi untuk kartu statistik halaman index.
     * Satu query agregat (group by status) + dua count ringan.
     */
    private function presensiStats($query, bool $isPersonal = false): array
    {
        // 1 pegawai = 1 status per hari.
        // Subquery: group by pegawai+tanggal, pick effective status per oranh.
        // Priority: hadir > telat > sakit > izin > cuti > alpa.
        $subQuery = (clone $query)
            ->select(
                'pegawai_id',
                'tanggal',
                DB::raw("CASE
                    WHEN SUM(CASE WHEN status = 'hadir' THEN 1 ELSE 0 END) > 0 THEN 'hadir'
                    WHEN SUM(CASE WHEN status = 'telat' THEN 1 ELSE 0 END) > 0 THEN 'telat'
                    WHEN SUM(CASE WHEN status = 'sakit' THEN 1 ELSE 0 END) > 0 THEN 'sakit'
                    WHEN SUM(CASE WHEN status = 'izin' THEN 1 ELSE 0 END) > 0 THEN 'izin'
                    WHEN SUM(CASE WHEN status = 'cuti' THEN 1 ELSE 0 END) > 0 THEN 'cuti'
                    ELSE 'alpa'
                END AS effective_status")
            )
            ->groupBy('pegawai_id', 'tanggal');

        $statusCounts = DB::table(DB::raw("({$subQuery->toSql()}) as person_days"))
            ->mergeBindings($subQuery->toBase())
            ->select('effective_status', DB::raw('COUNT(*) as count'))
            ->groupBy('effective_status')
            ->pluck('count', 'effective_status');

        $stats['total'] = $statusCounts->sum();
        $stats['hadir'] = $statusCounts->get('hadir', 0);
        $stats['telat'] = $statusCounts->get('telat', 0);
        $stats['sakit'] = $statusCounts->get('sakit', 0);
        $stats['izin'] = $statusCounts->get('izin', 0);
        $stats['cuti'] = $statusCounts->get('cuti', 0);
        $stats['alpa'] = $statusCounts->get('alpa', 0);
        $stats['lembur_pending'] = (clone $query)->where('is_lembur', true)->where('lembur_status', 'pending')->count();
        $stats['perlu_review'] = (clone $query)->where('lokasi_perlu_review', true)->count();

        if ($isPersonal) {
            // Mode pribadi: jangan pernah membandingkan dengan seluruh pegawai unit.
            $stats['belum_presensi'] = (clone $query)->exists() ? 0 : 1;

            return $stats;
        }

        // Belum presensi: total pegawai aktif di scope - yang sudah punya presensi.
        // $query sudah punya filter tanggal + unit/scope, jadi pegawai yang punya presensi
        // di rentang tanggal = COUNT(DISTINCT pegawai_id) dari query.
        $pegawaiWithPresensi = (clone $query)->distinct()->count('pegawai_id');
        $totalPegawai = $this->countActivePegawaiInScope($query);
        $stats['belum_presensi'] = max(0, $totalPegawai - $pegawaiWithPresensi);

        return $stats;
    }

    /**
     * Hitung total pegawai aktif yang termasuk dalam scope query presensi.
     * Mengekstrak scope unit/jenis dari query builder untuk diterapkan ke model Pegawai.
     */
    private function countActivePegawaiInScope($presensiQuery, ?Request $request = null): int
    {
        $user = auth()->user();
        $pegawais = Pegawai::where('status_aktif', 'aktif');

        // Terapkan scope unit yang sama dengan index() + filter unit dari request
        $request = $request ?? request();
        if ($request && $request->unit_id && $user->can('view_all_units')) {
            $pegawais->forUnit($request->unit_id);
        } elseif ($user->can('view_all_units')) {
            // Semua unit — tidak perlu filter
        } elseif ($user->unit_sekolah_id) {
            $pegawais->forUnit($user->unit_sekolah_id);
        }

        // Terapkan filter jenis juga agar stats konsisten
        if ($request && $request->jenis_filter === 'pendidik') {
            $pegawais->whereHas('jabatans', fn ($q) => $q->where('is_guru', true));
        } elseif ($request && $request->jenis_filter === 'kependidikan') {
            $pegawais->whereDoesntHave('jabatans', fn ($q) => $q->where('is_guru', true));
        }

        return $pegawais->count();
    }

    /**
     * Handle filter "belum_presensi": tampilkan pegawai aktif yang TIDAK punya
     * record presensi di rentang tanggal yang difilter.
     * Return presensi-shaped collection supaya frontend kompatibel.
     */
    /**
     * Mode Ringkas hanya merender baris presensi, sehingga JP yang dijadwalkan
     * tetapi tidak pernah diabsen (mis. pegawai pulang lebih dulu) tidak
     * terlihat sama sekali. Method ini menambahkan baris sintetis
     * "belum_presensi" untuk JP tersebut; keesokan harinya command
     * `presensi:finalize-alpa` mengubahnya menjadi baris alpa sungguhan.
     *
     * @param  Collection  $presensis
     * @return Collection
     */
    private function mergeJpTerlewat($presensis, Request $request, ?string $startDate, ?string $endDate)
    {
        // Filter detail tidak boleh disisipi baris yang tidak punya data presensi.
        foreach (['status_filter', 'lembur_filter', 'lokasi_filter', 'suspicious_filter', 'jadwal_filter'] as $filter) {
            if ($request->filled($filter)) {
                return $presensis;
            }
        }

        if (! $startDate || ! $endDate) {
            return $presensis;
        }

        $tanggalMulai = Carbon::parse($startDate)->startOfDay();
        $tanggalAkhir = Carbon::parse($endDate)->startOfDay();

        // Batasi rentang agar query jadwal tetap ringan.
        if ($tanggalMulai->diffInDays($tanggalAkhir) > 31) {
            return $presensis;
        }

        // Pegawai yang sama sekali belum punya presensi sudah ditangani filter
        // `status_filter=belum_presensi`, jadi cukup yang sudah punya baris.
        $pegawaiIds = $presensis->pluck('pegawai_id')->filter()->unique()->values();

        if ($pegawaiIds->isEmpty()) {
            return $presensis;
        }

        $hariIndo = [
            'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
        ];

        $tanggalList = [];
        for ($tanggal = $tanggalMulai->copy(); $tanggal->lte($tanggalAkhir); $tanggal->addDay()) {
            $tanggalList[$tanggal->toDateString()] = $hariIndo[$tanggal->format('l')] ?? null;
        }

        $jadwals = Jadwal::with(['pegawai', 'unitSekolah:id,nama', 'pegawaiMapel'])
            ->whereIn('pegawai_id', $pegawaiIds)
            ->whereIn('hari', array_values(array_unique(array_filter($tanggalList))))
            ->where('jenis_jadwal', 'mengajar')
            ->get()
            ->groupBy('pegawai_id');

        if ($jadwals->isEmpty()) {
            return $presensis;
        }

        // Pengecualian: hari libur, izin/cuti/sakit disetujui, dan tugas luar.
        // whereDate dipakai karena kolom tanggal pada HariLibur/PengajuanIzin
        // memakai cast `date` bawaan Laravel ('Y-m-d H:i:s') — perbandingan
        // string tanggal polos bisa gagal.
        $hariLibur = HariLibur::whereDate('tanggal', '>=', $tanggalMulai->toDateString())
            ->whereDate('tanggal', '<=', $tanggalAkhir->toDateString())
            ->get();
        $izins = PengajuanIzin::whereIn('pegawai_id', $pegawaiIds)
            ->where('status', 'disetujui')
            ->whereDate('tanggal_mulai', '<=', $tanggalAkhir->toDateString())
            ->whereDate('tanggal_selesai', '>=', $tanggalMulai->toDateString())
            ->get();
        $tugasLuar = Presensi::whereIn('pegawai_id', $pegawaiIds)
            ->whereDate('tanggal', '>=', $tanggalMulai->toDateString())
            ->whereDate('tanggal', '<=', $tanggalAkhir->toDateString())
            ->whereIn('tipe_presensi', ['tugas_luar', 'tugas_kantor'])
            ->where('status', '!=', 'alpa')
            ->get(['pegawai_id', 'tanggal']);

        $sudahAda = [];
        foreach ($presensis as $row) {
            if ($row->jadwal_id) {
                $sudahAda[$row->pegawai_id.'|'.$row->jadwal_id.'|'.$this->tanggalString($row->tanggal)] = true;
            }
        }

        $sintetis = [];

        foreach ($jadwals as $pegawaiId => $items) {
            $pegawai = $items->first()->pegawai;

            foreach ($items as $jadwal) {
                $unitId = $jadwal->unit_sekolah_id;

                foreach ($tanggalList as $tanggal => $hari) {
                    if ($jadwal->hari !== $hari) {
                        continue;
                    }

                    // JP hari ini yang belum selesai belum bisa disebut terlewat.
                    if (Carbon::parse($tanggal.' '.$jadwal->jam_selesai)->isFuture()) {
                        continue;
                    }

                    if (isset($sudahAda[$pegawaiId.'|'.$jadwal->id.'|'.$tanggal])) {
                        continue;
                    }

                    if ($hariLibur->contains(fn ($l) => $this->tanggalString($l->tanggal) === $tanggal
                        && ($l->unit_sekolah_id === null || $l->unit_sekolah_id === $unitId))) {
                        continue;
                    }

                    if ($izins->contains(fn ($i) => (int) $i->pegawai_id === (int) $pegawaiId
                        && $this->tanggalString($i->tanggal_mulai) <= $tanggal
                        && $this->tanggalString($i->tanggal_selesai) >= $tanggal)) {
                        continue;
                    }

                    if ($tugasLuar->contains(fn ($t) => (int) $t->pegawai_id === (int) $pegawaiId
                        && $this->tanggalString($t->tanggal) === $tanggal)) {
                        continue;
                    }

                    $sintetis[] = (object) [
                        'id' => 'jp-'.$jadwal->id.'-'.$tanggal,
                        'pegawai_id' => (int) $pegawaiId,
                        'jadwal_id' => $jadwal->id,
                        'unit_sekolah_id' => $unitId,
                        'is_sintetis' => true,
                        'tipe_presensi' => 'mengajar',
                        'is_tugas_luar' => false,
                        'tanggal' => $tanggal,
                        'jam_masuk' => null,
                        'jam_keluar' => null,
                        'status' => 'belum_presensi',
                        'is_lembur' => false,
                        'lembur_status' => null,
                        'lokasi_perlu_review' => false,
                        'posisi_mencurigakan' => false,
                        'foto' => null,
                        'foto_masuk_url' => null,
                        'foto_keluar_url' => null,
                        'foto_kegiatan_urls' => [],
                        'keterangan' => null,
                        'unit_lokasi_masuk' => null,
                        'unit_lokasi_keluar' => null,
                        'pegawai' => $pegawai,
                        'jadwal' => $jadwal,
                        'unit_sekolah' => $jadwal->unitSekolah,
                    ];
                }
            }
        }

        if ($sintetis === []) {
            return $presensis;
        }

        return $presensis
            ->concat($sintetis)
            ->sortByDesc(fn ($row) => $this->tanggalString($row->tanggal))
            ->values();
    }

    /**
     * Normalisasi tanggal (Carbon atau string) menjadi format Y-m-d.
     */
    private function tanggalString($tanggal): string
    {
        return $tanggal instanceof \DateTimeInterface ? $tanggal->format('Y-m-d') : (string) $tanggal;
    }

    private function handleBelumPresensi(Request $request, $user, array $stats, bool $isPersonal = false, bool $isPimpinan = false): Response
    {
        $startDate = $request->start_date ?? Carbon::today()->toDateString();
        $endDate = $request->end_date ?? Carbon::today()->toDateString();

        $pegawaisWithPresensi = Presensi::whereBetween('tanggal', [$startDate, $endDate])
            ->distinct()->pluck('pegawai_id');

        $pegawais = Pegawai::where('status_aktif', 'aktif')
            ->with(['units', 'jabatans'])
            ->whereNotIn('id', $pegawaisWithPresensi);

        $selfPegawai = $isPersonal ? Pegawai::where('user_id', auth()->id())->first() : null;

        // Terapkan scope yang sama dengan index().
        if ($isPersonal) {
            // Mode pribadi: hanya diri sendiri.
            if ($selfPegawai) {
                $pegawais->where('id', $selfPegawai->id);
            } else {
                $pegawais->where('id', -1);
            }
        } elseif ($isPimpinan) {
            $this->scopePimpinanBawahan($pegawais, $user);
        } elseif ($user->can('view_all_units')) {
            // Semua unit
        } elseif ($user->unit_sekolah_id) {
            $pegawais->forUnit($user->unit_sekolah_id);
        }

        // Filter search
        if ($request->search) {
            $pegawais->where('nama_lengkap', 'like', '%'.$request->search.'%');
        }

        // Filter jenis
        if ($request->jenis_filter === 'pendidik') {
            $pegawais->whereHas('jabatans', fn ($q) => $q->where('is_guru', true));
        } elseif ($request->jenis_filter === 'kependidikan') {
            $pegawais->whereDoesntHave('jabatans', fn ($q) => $q->where('is_guru', true));
        }

        // Filter unit
        if ($request->unit_id && $user->can('view_all_units')) {
            $pegawais->forUnit($request->unit_id);
        }

        $pegawaiList = $pegawais->orderBy('nama_lengkap')->get();

        // Buat virtual presensi records supaya frontend bisa render.
        // Unit di-resolve primary-aware (pivot is_primary → pertama → user.unit_sekolah_id)
        // supaya pegawai tanpa pivot tetap menampilkan unit dari User, bukan '—'.
        // Semua unit di-prefetch sekali (hindari N+1 di fallback find()).
        $unitsById = UnitSekolah::all()->keyBy('id');
        $virtualPresensis = $pegawaiList->map(function ($p) use ($startDate, $unitsById) {
            $unit = $p->units->sortByDesc(fn ($u) => $u->pivot->is_primary ?? false)->first()
                ?? $unitsById->get($p->user?->unit_sekolah_id);

            return (object) [
                'id' => 'new-'.$p->id,
                'pegawai_id' => $p->id,
                'tanggal' => $startDate,
                'jam_masuk' => null,
                'jam_keluar' => null,
                'status' => 'belum_presensi',
                'is_lembur' => false,
                'lembur_status' => null,
                'lokasi_perlu_review' => false,
                'posisi_mencurigakan' => false,
                'foto' => null,
                'keterangan' => null,
                'pegawai' => $p,
                'jadwal' => null,
                // Key snake_case agar konsisten dgn relasi Presensi asli yang dibaca
                // frontend sebagai parent.unit_sekolah (bukan unitSekolah — mismatch
                // key inilah penyebab unit selalu '—' di daftar Belum Presensi).
                'unit_sekolah' => $unit,
            ];
        });

        $paginator = new LengthAwarePaginator(
            $virtualPresensis, $virtualPresensis->count(), $virtualPresensis->count(), 1, ['path' => $request->url()]
        );

        $units = [];
        if ($user->can('view_all_units')) {
            $units = UnitSekolah::orderBy('nama')->get();
        }

        // Update stats untuk total
        $stats['belum_presensi'] = $pegawaiList->count();

        return inertia('Presensi/Index', [
            'presensis' => $paginator,
            'pegawai' => $isPersonal ? $selfPegawai : null,
            'filters' => $request->only(['start_date', 'end_date', 'unit_id', 'lembur_filter', 'lokasi_filter', 'suspicious_filter', 'status_filter', 'jadwal_filter', 'jenis_filter', 'search', 'view_mode']),
            'units' => $units,
            'userRole' => $user->roles->first()?->name ?? 'pegawai',
            'stats' => $stats,
            'is_personal' => $isPersonal,
            'is_pimpinan' => $isPimpinan,
        ]);
    }

    /**
     * Export PDF daftar pegawai aktif yang belum presensi pada rentang tanggal.
     * Filter (tanggal, unit, jenis, search) sama dengan handleBelumPresensi().
     */
    public function exportBelumPresensiPdf(Request $request)
    {
        $user = auth()->user();
        if (! $user || ! $user->can('view_presensi')) {
            abort(403, 'Akses ditolak.');
        }

        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'unit_id' => 'nullable|integer|exists:unit_sekolah,id',
            'jenis_filter' => 'nullable|in:pendidik,kependidikan',
            'search' => 'nullable|string|max:100',
        ]);

        $startDate = $validated['start_date'] ?? Carbon::today()->toDateString();
        $endDate = $validated['end_date'] ?? $startDate;

        $pegawaisWithPresensi = Presensi::whereBetween('tanggal', [$startDate, $endDate])
            ->distinct()->pluck('pegawai_id');

        $pegawais = Pegawai::where('status_aktif', 'aktif')
            ->with(['units', 'jabatans'])
            ->whereNotIn('id', $pegawaisWithPresensi);

        // Scope unit sama dengan handleBelumPresensi()
        if ($user->can('view_all_units')) {
            // Semua unit
        } elseif ($user->unit_sekolah_id) {
            $pegawais->forUnit($user->unit_sekolah_id);
        }

        if (! empty($validated['search'])) {
            $pegawais->where('nama_lengkap', 'like', '%'.$validated['search'].'%');
        }

        if (($validated['jenis_filter'] ?? null) === 'pendidik') {
            $pegawais->whereHas('jabatans', fn ($q) => $q->where('is_guru', true));
        } elseif (($validated['jenis_filter'] ?? null) === 'kependidikan') {
            $pegawais->whereDoesntHave('jabatans', fn ($q) => $q->where('is_guru', true));
        }

        if (! empty($validated['unit_id']) && $user->can('view_all_units')) {
            $pegawais->forUnit($validated['unit_id']);
        }

        $pegawaiList = $pegawais->orderBy('nama_lengkap')->get();

        // Kop surat: unit terpilih (atau auto-resolved dari scope user) → data unit itu;
        // semua unit (superadmin) → unit induk "Yayasan"; fallback terakhir → config env.
        $kopUnit = null;
        if (! empty($validated['unit_id'])) {
            $kopUnit = UnitSekolah::find($validated['unit_id']);
        } elseif ($user->unit_sekolah_id && ! $user->can('view_all_units')) {
            $kopUnit = UnitSekolah::find($user->unit_sekolah_id);
        }
        if (! $kopUnit) {
            $kopUnit = UnitSekolah::where('nama', 'like', 'Yayasan%')->first();
        }
        $unitName = $kopUnit?->nama ?? 'Semua Unit Sekolah';

        $logoPath = $this->resolveLogoPath($kopUnit) ?? $this->resolveYayasanLogoPath();
        $logoWidth = null;
        if ($logoPath && file_exists($logoPath)) {
            $sz = @getimagesize($logoPath);
            if ($sz) {
                $logoWidth = (int) round(64 * $sz[0] / $sz[1]);
            }
        }

        $kop = [
            'name' => $kopUnit?->nama ? strtoupper($kopUnit->nama) : config('yayasan.name'),
            'tagline' => config('yayasan.tagline'),
            'address' => $kopUnit?->alamat ?: config('yayasan.address'),
            'phone' => $kopUnit?->telepon ?: config('yayasan.phone'),
            'email' => config('yayasan.email'),
            'website' => $kopUnit?->web ?: config('yayasan.website'),
        ];

        $periodeStr = $startDate === $endDate
            ? Carbon::parse($startDate)->translatedFormat('d F Y')
            : Carbon::parse($startDate)->translatedFormat('d/m/Y').' s/d '.Carbon::parse($endDate)->translatedFormat('d/m/Y');

        $rows = $pegawaiList->map(fn ($p, $i) => [
            'no' => $i + 1,
            'nip' => $p->nuptk ?? '-',
            'nama' => $p->nama_lengkap,
            'unit' => $p->units->pluck('nama')->implode(', ') ?: '-',
            'jabatan' => $p->jabatans->pluck('nama')->implode(', ') ?: '-',
            'status_pegawai' => $p->status_pegawai ?? '-',
        ])->all();

        try {
            $pdf = Pdf::loadView('exports.pdf-belum-presensi', compact('rows', 'periodeStr', 'unitName', 'logoPath', 'logoWidth', 'kop'))
                ->setPaper('A4', 'portrait');

            $unitSlug = $kopUnit?->singkatan ?? preg_replace('/[^A-Za-z0-9]+/', '_', $unitName);

            return $pdf->download('Daftar_Belum_Presensi_'.$unitSlug.'_'.$startDate.'.pdf');
        } catch (\Throwable $e) {
            \Log::error('PDF belum presensi gagal', [
                'message' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
            ]);

            return response()->json(['message' => 'PDF gagal dibuat: '.substr($e->getMessage(), 0, 300)], 500);
        }
    }

    /**
     * Export PDF rekapitulasi presensi harian (format: kartu ringkasan + tabel guru + detail kelas).
     * Mirip laporan yang diminta user — menampilkan semua guru beserta jadwal kelas hari itu.
     */
    public function exportRekapPresensiPdf(Request $request)
    {
        $user = auth()->user();
        if (! $user || ! $user->can('view_presensi')) {
            abort(403, 'Akses ditolak.');
        }

        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'unit_id' => 'nullable|integer|exists:unit_sekolah,id',
            'jenis_filter' => 'nullable|in:pendidik,kependidikan',
            'search' => 'nullable|string|max:100',
        ]);

        $startDate = $validated['start_date'] ?? Carbon::today()->toDateString();
        $endDate = $validated['end_date'] ?? $startDate;

        // ── Resolve unit / kop ──
        $kopUnit = null;
        if (! empty($validated['unit_id'])) {
            $kopUnit = UnitSekolah::find($validated['unit_id']);
        } elseif ($user->unit_sekolah_id && ! $user->can('view_all_units')) {
            $kopUnit = UnitSekolah::find($user->unit_sekolah_id);
        }
        if (! $kopUnit) {
            $kopUnit = UnitSekolah::where('nama', 'like', 'Yayasan%')->first();
        }
        $unitName = $kopUnit?->nama ?? 'Semua Unit Sekolah';

        $logoPath = $this->resolveLogoPath($kopUnit) ?? $this->resolveYayasanLogoPath();
        $logoWidth = null;
        if ($logoPath && file_exists($logoPath)) {
            $sz = @getimagesize($logoPath);
            if ($sz) {
                $logoWidth = (int) round(64 * $sz[0] / $sz[1]);
            }
        }

        $kop = [
            'name' => $kopUnit?->nama ? strtoupper($kopUnit->nama) : config('yayasan.name'),
            'tagline' => config('yayasan.tagline'),
            'address' => $kopUnit?->alamat ?: config('yayasan.address'),
            'phone' => $kopUnit?->telepon ?: config('yayasan.phone'),
            'email' => config('yayasan.email'),
            'website' => $kopUnit?->web ?: config('yayasan.website'),
        ];

        $periodeStr = $startDate === $endDate
            ? Carbon::parse($startDate)->translatedFormat('d F Y')
            : Carbon::parse($startDate)->translatedFormat('d/m/Y').' s/d '.Carbon::parse($endDate)->translatedFormat('d/m/Y');

        // ── Query pegawai aktif ──
        $pegawais = Pegawai::where('status_aktif', 'aktif')->with(['units', 'jabatans']);

        if ($user->can('view_all_units')) {
            // semua unit
        } elseif ($user->unit_sekolah_id) {
            $pegawais->forUnit($user->unit_sekolah_id);
        }

        if (! empty($validated['unit_id']) && $user->can('view_all_units')) {
            $pegawais->forUnit($validated['unit_id']);
        }

        if (($validated['jenis_filter'] ?? null) === 'pendidik') {
            $pegawais->whereHas('jabatans', fn ($q) => $q->where('is_guru', true));
        } elseif (($validated['jenis_filter'] ?? null) === 'kependidikan') {
            $pegawais->whereDoesntHave('jabatans', fn ($q) => $q->where('is_guru', true));
        }

        if (! empty($validated['search'])) {
            $pegawais->where('nama_lengkap', 'like', '%'.$validated['search'].'%');
        }

        $pegawaiList = $pegawais->orderBy('nama_lengkap')->get();

        // ── Presensi untuk rentang tanggal ──
        $presensiMap = Presensi::whereBetween('tanggal', [$startDate, $endDate])
            ->with('jadwal')
            ->get()
            ->groupBy('pegawai_id');

        // ── Jadwal guru untuk hari pertama dalam rentang (biasanya 1 hari) ──
        $hariMap = [
            0 => 'Minggu', 1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu',
            4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu',
        ];
        $hariIni = $hariMap[Carbon::parse($startDate)->dayOfWeek];

        $jadwalMap = Jadwal::whereIn('pegawai_id', $pegawaiList->pluck('id'))
            ->where('hari', $hariIni)
            ->orderBy('jam_mulai')
            ->get()
            ->groupBy('pegawai_id');

        // ── Build rows ──
        $rows = $pegawaiList->map(function ($p) use ($presensiMap, $jadwalMap) {
            $presensiList = $presensiMap->get($p->id, collect());
            $jadwals = $jadwalMap->get($p->id, collect());

            // Status presensi gabungan
            $hasHadir = $presensiList->contains('status', 'hadir');
            $hasTelat = $presensiList->contains('status', 'telat');
            $hasSakit = $presensiList->contains('status', 'sakit');
            $hasIzin = $presensiList->contains('status', 'izin');
            $hasCuti = $presensiList->contains('status', 'cuti');

            if ($presensiList->isEmpty()) {
                $statusPresensi = 'Belum Presensi';
            } elseif ($hasHadir) {
                $statusPresensi = 'Hadir Tepat Waktu';
            } elseif ($hasTelat) {
                $statusPresensi = 'Terlambat';
            } elseif ($hasSakit) {
                $statusPresensi = 'Sakit';
            } elseif ($hasIzin) {
                $statusPresensi = 'Izin';
            } elseif ($hasCuti) {
                $statusPresensi = 'Cuti';
            } else {
                $statusPresensi = 'Alpa';
            }

            // Jam masuk / pulang
            $jamMasuk = $presensiList->whereNotNull('jam_masuk')->sortBy('jam_masuk')->first()?->jam_masuk;
            $jamKeluar = $presensiList->whereNotNull('jam_keluar')->sortByDesc('jam_keluar')->first()?->jam_keluar;

            // Format jam
            $jamMasukStr = $jamMasuk ? Carbon::parse($jamMasuk)->format('H:i:s').' WIB' : '-';
            $jamKeluarStr = $jamKeluar ? Carbon::parse($jamKeluar)->format('H:i:s').' WIB' : '-';

            // Daftar kelas hari ini
            $kelasList = $jadwals->map(function ($j) {
                $mapel = $j->mata_pelajaran?->nama ?? '-';
                $ruangan = $j->unitSekolah?->singkatan ?? '';

                return $j->kelas_label.' ('.$mapel.($ruangan ? ' • '.$ruangan : '').')';
            })->implode(', ');

            // Status mengajar
            $totalKelas = $jadwals->count();
            $selesaiKelas = $presensiList->where('jadwal_id', '!=', null)->count();
            $statusMengajar = $totalKelas > 0 ? $selesaiKelas.'/'.$totalKelas.' Selesai' : '-';

            return [
                'nip' => $p->nuptk ?? '-',
                'nama' => $p->nama_lengkap,
                'unit' => $p->units->pluck('nama')->implode(', ') ?: '-',
                'jabatan' => $p->jabatans->pluck('nama')->implode(', ') ?: '-',
                'jam_masuk' => $jamMasukStr,
                'jam_pulang' => $jamKeluarStr,
                'status_presensi' => $statusPresensi,
                'kelas_list' => $kelasList ?: '-',
                'status_mengajar' => $statusMengajar,
                'detail_kelas' => $jadwals->map(function ($j) use ($presensiList) {
                    $presensiMatch = $presensiList->firstWhere('jadwal_id', $j->id);

                    return [
                        'jam_sesi' => $j->jam_mulai.' - '.$j->jam_selesai,
                        'kelas_ruangan' => $j->kelas_label,
                        'mata_pelajaran' => $j->mata_pelajaran?->nama ?? '-',
                        'status' => $presensiMatch
                            ? ($presensiMatch->status === 'telat' ? 'Terlambat '.Carbon::parse($presensiMatch->jam_masuk)->diffInMinutes(Carbon::parse($j->jam_mulai)).'m' : 'Tepat Waktu')
                            : 'Tidak Mengajar',
                    ];
                })->all(),
            ];
        })->all();

        // ── Summary stats ──
        $totalGuru = count($rows);
        $hadirCount = collect($rows)->filter(fn ($r) => str_contains($r['status_presensi'], 'Hadir') || str_contains($r['status_presensi'], 'Terlambat'))->count();
        $izinCount = collect($rows)->filter(fn ($r) => in_array($r['status_presensi'], ['Sakit', 'Izin']))->count();
        $alpaCount = collect($rows)->filter(fn ($r) => $r['status_presensi'] === 'Belum Presensi' || $r['status_presensi'] === 'Alpa')->count();
        $totalKelasHari = collect($rows)->sum(function ($r) {
            return (int) explode('/', $r['status_mengajar'])[0] ?? 0;
        });
        $selesaiKelasHari = collect($rows)->sum(function ($r) {
            $parts = explode('/', $r['status_mengajar']);

            return (int) ($parts[0] ?? 0);
        });

        $stats = [
            'hadir' => $hadirCount,
            'total_guru' => $totalGuru,
            'izin_sakit_alpa' => $izinCount + $alpaCount,
            'total_kelas' => $totalKelasHari,
            'selesai_kelas' => $selesaiKelasHari,
        ];

        try {
            $pdf = Pdf::loadView('exports.pdf-rekap-presensi', compact(
                'rows', 'periodeStr', 'unitName', 'logoPath', 'logoWidth', 'kop', 'stats'
            ))->setPaper('A4', 'landscape');

            $unitSlug = $kopUnit?->singkatan ?? preg_replace('/[^A-Za-z0-9]+/', '_', $unitName);

            return $pdf->download('Rekap_Presensi_'.$unitSlug.'_'.$startDate.'.pdf');
        } catch (\Throwable $e) {
            \Log::error('PDF rekap presensi gagal', [
                'message' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
            ]);

            return response()->json(['message' => 'PDF gagal dibuat: '.substr($e->getMessage(), 0, 300)], 500);
        }
    }

    public function exportPersonalPresensiExcel(PresensiPersonalReportRequest $request)
    {
        $pegawai = $this->personalPegawai();
        $validated = $request->validated();
        [$start, $end] = $this->personalReportRange($validated);

        $filename = 'Laporan_Presensi_Pribadi_'.Str::slug($pegawai->nama_lengkap)
            .'_'.$start->toDateString().'_to_'.$end->toDateString().'.xlsx';

        return Excel::download(
            new LaporanPresensiExport(
                $start->toDateString(),
                $end->toDateString(),
                null,
                null,
                null,
                null,
                $validated['status'] ?? null,
                null,
                $pegawai->id,
            ),
            $filename,
        );
    }

    public function exportPersonalPresensiPdf(
        PresensiPersonalReportRequest $request,
        PresensiPersonalReportService $reportService,
    ) {
        $pegawai = $this->personalPegawai();
        $validated = $request->validated();
        [$start, $end] = $this->personalReportRange($validated);
        $status = $validated['status'] ?? null;

        $summary = $reportService->summary($pegawai, $start, $end, $status);

        try {
            $result = app(LaporanPdfService::class)->generatePresensiPribadi(
                [
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                    'status' => $status,
                ],
                $pegawai,
                $summary,
            );

            return response($result['content'], 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$result['filename'].'"',
                'Content-Length' => strlen($result['content']),
            ]);
        } catch (PdfTooLargeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    private function personalPegawai(): Pegawai
    {
        $pegawai = Pegawai::where('user_id', auth()->id())->first();
        abort_unless($pegawai, 403, 'Akun ini tidak terhubung dengan data pegawai.');

        return $pegawai;
    }

    private function personalReportRange(array $validated): array
    {
        $start = Carbon::parse($validated['start_date'] ?? Carbon::today())->startOfDay();
        $end = Carbon::parse($validated['end_date'] ?? $start->toDateString())->startOfDay();

        return [$start, $end];
    }

    /**
     * Resolve logo unit ke path file lokal (bukan URL) agar bisa dirender DOMPDF.
     * Null bila unit tidak punya logo atau file tidak ada.
     */
    private function resolveLogoPath(?UnitSekolah $unit): ?string
    {
        if (! $unit?->logo) {
            return null;
        }

        $disk = config('filesystems.image_disk', 'public');
        $root = config("filesystems.disks.$disk.root");
        $path = rtrim($root, '/').'/'.ltrim($unit->logo, '/');

        return file_exists($path) ? $path : null;
    }

    /**
     * Resolve logo yayasan (config kcd.yayasan_logo) ke path file lokal untuk DOMPDF.
     */
    private function resolveYayasanLogoPath(): ?string
    {
        $rel = config('kcd.yayasan_logo');
        if (! $rel) {
            return null;
        }

        $public = public_path($rel);
        if (file_exists($public)) {
            return $public;
        }

        $disk = config('filesystems.image_disk', 'public');
        $root = config("filesystems.disks.$disk.root");
        $path = rtrim($root, '/').'/'.ltrim($rel, '/');

        return file_exists($path) ? $path : null;
    }

    public function create()
    {
        $isAdmin = auth()->user() && auth()->user()->can('view_presensi');
        if (! $isAdmin) {
            abort(403, 'Akses ditolak. Presensi hanya bisa dilakukan via Mobile Portal.');
        }

        $pegawai = Pegawai::first(); // Mock user for simulation only
        $hariMap = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
        $hariIniIndo = $hariMap[Carbon::now()->format('l')];

        $jadwalHariIni = Jadwal::with('unitSekolah')
            ->where('pegawai_id', $pegawai->id ?? 0)
            ->where('hari', $hariIniIndo)
            ->get();

        $presensiHariIni = Presensi::where('pegawai_id', $pegawai->id ?? 0)
            ->where('tanggal', Carbon::today()->toDateString())
            ->get();

        return inertia('Presensi/Create', [
            'jadwals' => $jadwalHariIni,
            'presensis' => $presensiHariIni,
            'pegawai' => $pegawai,
        ]);
    }

    public function store(Request $request)
    {
        $isAdmin = auth()->user() && auth()->user()->can('view_presensi');
        if (! $isAdmin) {
            abort(403, 'Akses ditolak. Presensi hanya bisa dilakukan via Mobile Portal.');
        }

        $request->validate([
            'pegawai_id' => 'required|exists:pegawai,id',
            'jadwal_id' => 'required|exists:jadwal,id',
            'tipe' => 'required|in:masuk,keluar',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'foto' => ['required', 'string', 'max:'.PresensiMessages::MAX_FOTO_BASE64, 'regex:/^data:image\/\w+;base64,/'],
        ]);

        $jadwal = Jadwal::with(['unitSekolah', 'unitLokasi'])->findOrFail($request->jadwal_id);
        $unit = $jadwal->unitLokasi ?? $jadwal->unitSekolah;

        // Admin boleh input/ralat presensi di hari apa pun (termasuk Minggu /
        // Sabtu / hari libur) — blokir hanya berlaku di portal mobile pegawai.

        // Validasi Geofencing
        $distance = $this->calculateDistance($request->latitude, $request->longitude, $unit->latitude, $unit->longitude);

        if ($distance > $unit->radius_meter) {
            return back()->withErrors(['geofence' => sprintf(PresensiMessages::GEOFENCE_OUTSIDE, $distance, $unit->radius_meter)]);
        }

        $pegawai = Pegawai::findOrFail($request->pegawai_id);

        $user = auth()->user();
        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units') && ! $pegawai->belongsToUnit($user->unit_sekolah_id)) {
            abort(403, 'Akses ditolak.');
        }

        $tempName = Str::uuid()->toString().'.jpg';
        $tempPath = 'temp/'.$tempName;
        $base64Data = $request->foto;
        Storage::disk('local')->put($tempPath, base64_decode(explode(',', $base64Data, 2)[1] ?? ''));

        try {
            $presensi = DB::transaction(function () use ($request, $unit, $distance) {
                $presensi = Presensi::where('pegawai_id', $request->pegawai_id)
                    ->where('jadwal_id', $request->jadwal_id)
                    ->where('tanggal', Carbon::today()->toDateString())
                    ->lockForUpdate()
                    ->first();

                if (! $presensi) {
                    $presensi = new Presensi([
                        'pegawai_id' => $request->pegawai_id,
                        'jadwal_id' => $request->jadwal_id,
                        'tanggal' => Carbon::today()->toDateString(),
                    ]);
                }

                $jadwal = Jadwal::find($request->jadwal_id);

                $presensi->unit_sekolah_id = $jadwal->unit_sekolah_id;

                if ($request->tipe === 'masuk') {
                    if ($presensi->jam_masuk) {
                        throw ValidationException::withMessages(['conflict' => PresensiMessages::SUDAH_ABSEN_MASUK]);
                    }
                    $presensi->jam_masuk = Carbon::now()->format('H:i:s');
                    $presensi->latitude_masuk = $request->latitude;
                    $presensi->longitude_masuk = $request->longitude;
                    $presensi->foto_masuk_status = 'pending';
                    $presensi->jarak_masuk_meter = $distance;

                    $presensi->status = Presensi::statusAt(Carbon::now()->format('H:i:s'), $jadwal->jam_mulai, (int) $unit->toleransi_menit);
                } else {
                    if (! $presensi->exists || ! $presensi->jam_masuk) {
                        throw ValidationException::withMessages(['conflict' => PresensiMessages::BELUM_ABSEN_MASUK]);
                    }
                    if ($presensi->jam_keluar) {
                        throw ValidationException::withMessages(['conflict' => PresensiMessages::SUDAH_ABSEN_KELUAR]);
                    }
                    $presensi->jam_keluar = Carbon::now()->format('H:i:s');
                    $presensi->latitude_keluar = $request->latitude;
                    $presensi->longitude_keluar = $request->longitude;
                    $presensi->foto_keluar_status = 'pending';
                    $presensi->jarak_keluar_meter = $distance;
                }

                $presensi->save();

                return $presensi;
            });
        } catch (\Throwable $e) {
            // Jangan tinggalkan sampah temp foto saat transaksi/validasi gagal.
            if (Storage::disk('local')->exists($tempPath)) {
                Storage::disk('local')->delete($tempPath);
            }

            throw $e;
        }

        Bus::dispatchSync(new ProcessPresensiFoto(
            $presensi->id,
            $request->tipe,
            $tempPath,
            'presensi',
            null,
            ['id' => $pegawai->id, 'nama' => $pegawai->nama_lengkap]
        ));

        return redirect()->route('presensi.index')->with('message', 'Presensi berhasil dicatat.');
    }

    public function update(Request $request, $id)
    {
        $user = auth()->user();
        $isAdmin = $user && $user->can('view_presensi');
        if (! $isAdmin) {
            abort(403);
        }

        $request->validate([
            'status' => 'required|in:hadir,telat,alpa,sakit,izin,cuti',
            'persentase_bayar_jam' => 'nullable|integer|min:0|max:100',
        ]);

        $statusLama = null;
        $presensi = DB::transaction(function () use ($id, $request, $user, &$statusLama) {
            $presensi = Presensi::with('pegawai')
                ->lockForUpdate()
                ->findOrFail($id);

            if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units') && ! $presensi->pegawai->belongsToUnit($user->unit_sekolah_id)) {
                abort(403, 'Akses ditolak.');
            }

            if (PayrollLockHelper::isPeriodLocked($presensi->pegawai_id, $presensi->tanggal)) {
                throw ValidationException::withMessages(['error' => 'Periode penggajian sudah dikunci. Tidak bisa mengubah status presensi.']);
            }

            $statusLama = $presensi->status;
            $presensi->status = $request->status;
            if ($request->filled('persentase_bayar_jam')) {
                $presensi->persentase_bayar_jam = (int) $request->persentase_bayar_jam;
            }
            $presensi->save();

            return $presensi;
        });

        AuditPresensi::log($presensi->id, 'ubah_status', 'status', $statusLama, $request->status);

        return redirect()->back()->with('message', 'Status presensi berhasil diubah menjadi '.strtoupper($request->status));
    }

    public function approveLembur($id)
    {
        $user = auth()->user();
        if (! $user || ! $user->can('view_presensi')) {
            abort(403);
        }

        // Transaction + lockForUpdate: cegah race dua admin approve/reject bersamaan
        // (TOCTOU antara check status dan update).
        DB::transaction(function () use ($id, $user) {
            $presensi = Presensi::with(['pegawai.units', 'pegawai.user'])
                ->lockForUpdate()
                ->findOrFail($id);

            if (! $presensi->is_lembur || $presensi->lembur_status !== 'pending') {
                throw ValidationException::withMessages(['error' => 'Hanya lembur dengan status pending yang bisa disetujui.']);
            }

            if ($user->unit_sekolah_id && ! $user->can('view_all_units') && ! $presensi->pegawai->belongsToUnit($user->unit_sekolah_id)) {
                abort(403, 'Akses ditolak.');
            }

            if (PayrollLockHelper::isPeriodLocked($presensi->pegawai_id, $presensi->tanggal)) {
                throw ValidationException::withMessages(['error' => 'Periode penggajian sudah dikunci.']);
            }

            // Durasi lembur harus lengkap (sudah absen keluar) agar bisa dibatasi
            // junit.max_lembur_jam — tanpa jam_keluar durasi tak bisa dipercaya.
            if (! $presensi->jam_keluar) {
                throw ValidationException::withMessages(['error' => 'Belum ada absen keluar lembur. Approve setelah pegawai mengisi absen keluar lembur.']);
            }

            $durasiJam = round((strtotime($presensi->jam_keluar) - strtotime($presensi->jam_masuk)) / 3600, 2);
            $maxJam = $presensi->pegawai->primaryUnit()?->max_lembur_jam;
            if ($maxJam !== null && $durasiJam > (float) $maxJam) {
                throw ValidationException::withMessages(['error' => "Jumlah jam lembur ({$durasiJam} jam) melebihi batas unit ({$maxJam} jam)."]);
            }

            // lembur_status ada di $guarded → update([...]) silent drop.
            // Set langsung + save() agar perubahan benar-benar tersimpan.
            $presensi->lembur_status = 'disetujui';
            $presensi->save();

            AuditPresensi::log($presensi->id, 'approve_lembur', 'lembur_status', 'pending', 'disetujui');

            NotificationHelper::sendSafely($presensi->pegawai?->user, new StatusLembur($presensi->withoutRelations(), 'disetujui'));
        });

        return redirect()->back()->with('message', 'Lembur berhasil disetujui.');
    }

    public function rejectLembur($id)
    {
        $user = auth()->user();
        if (! $user || ! $user->can('view_presensi')) {
            abort(403);
        }

        DB::transaction(function () use ($id, $user) {
            $presensi = Presensi::with('pegawai.user')
                ->lockForUpdate()
                ->findOrFail($id);

            if (! $presensi->is_lembur || $presensi->lembur_status !== 'pending') {
                throw ValidationException::withMessages(['error' => 'Hanya lembur dengan status pending yang bisa ditolak.']);
            }

            if ($user->unit_sekolah_id && ! $user->can('view_all_units') && ! $presensi->pegawai->belongsToUnit($user->unit_sekolah_id)) {
                abort(403, 'Akses ditolak.');
            }

            if (PayrollLockHelper::isPeriodLocked($presensi->pegawai_id, $presensi->tanggal)) {
                throw ValidationException::withMessages(['error' => 'Periode penggajian sudah dikunci.']);
            }

            $presensi->lembur_status = 'ditolak';
            $presensi->save();

            AuditPresensi::log($presensi->id, 'reject_lembur', 'lembur_status', 'pending', 'ditolak');

            NotificationHelper::sendSafely($presensi->pegawai?->user, new StatusLembur($presensi->withoutRelations(), 'ditolak'));
        });

        return redirect()->back()->with('message', 'Lembur ditolak.');
    }

    public function approveTugasLuar($id)
    {
        $user = auth()->user();
        if (! $user || ! $user->can('view_presensi')) {
            abort(403);
        }

        DB::transaction(function () use ($id, $user) {
            $presensi = Presensi::with('pegawai')
                ->lockForUpdate()
                ->findOrFail($id);

            if (! $presensi->is_tugas_luar || $presensi->tugas_luar_status !== 'pending') {
                throw ValidationException::withMessages(['error' => 'Hanya tugas luar dengan status pending yang bisa disetujui.']);
            }

            if ($user->unit_sekolah_id && ! $user->can('view_all_units') && ! $presensi->pegawai->belongsToUnit($user->unit_sekolah_id)) {
                abort(403, 'Akses ditolak.');
            }

            if (PayrollLockHelper::isPeriodLocked($presensi->pegawai_id, $presensi->tanggal)) {
                throw ValidationException::withMessages(['error' => 'Periode penggajian sudah dikunci.']);
            }

            $presensi->update(['tugas_luar_status' => 'disetujui']);

            AuditPresensi::log($presensi->id, 'approve_tugas_luar', 'tugas_luar_status', 'pending', 'disetujui');
        });

        return redirect()->back()->with('message', 'Tugas luar berhasil disetujui.');
    }

    public function rejectTugasLuar($id)
    {
        $user = auth()->user();
        if (! $user || ! $user->can('view_presensi')) {
            abort(403);
        }

        DB::transaction(function () use ($id, $user) {
            $presensi = Presensi::with('pegawai')
                ->lockForUpdate()
                ->findOrFail($id);

            if (! $presensi->is_tugas_luar || $presensi->tugas_luar_status !== 'pending') {
                throw ValidationException::withMessages(['error' => 'Hanya tugas luar dengan status pending yang bisa ditolak.']);
            }

            if ($user->unit_sekolah_id && ! $user->can('view_all_units') && ! $presensi->pegawai->belongsToUnit($user->unit_sekolah_id)) {
                abort(403, 'Akses ditolak.');
            }

            if (PayrollLockHelper::isPeriodLocked($presensi->pegawai_id, $presensi->tanggal)) {
                throw ValidationException::withMessages(['error' => 'Periode penggajian sudah dikunci.']);
            }

            // status ada di $guarded -> set via properti (bypass mass-assignment guard).
            $presensi->tugas_luar_status = 'ditolak';
            $presensi->status = 'alpa';
            $presensi->save();

            AuditPresensi::log($presensi->id, 'reject_tugas_luar', 'tugas_luar_status', 'pending', 'ditolak');
            AuditPresensi::log($presensi->id, 'reject_tugas_luar', 'status', 'hadir', 'alpa');
        });

        return redirect()->back()->with('message', 'Tugas luar ditolak.');
    }

    /**
     * Simpan foto bukti kegiatan tugas luar (diambil saat dinas, mis. rapat).
     * Opsional, bisa beberapa foto. Race-safe: baris presensi di-lock dalam
     * transaksi sebelum append ke array foto_kegiatan (hindari lost update
     * bila pegawai kirim beberapa foto berbarengan).
     */
    public function storeBuktiTugasLuar(Request $request, Presensi $presensi)
    {
        $user = auth()->user();
        $pegawai = $user?->pegawai;
        if (! $pegawai) {
            abort(403);
        }
        if ($presensi->pegawai_id !== $pegawai->id || ! $presensi->is_tugas_luar) {
            abort(403);
        }

        $request->validate([
            'foto' => ['required', 'string', 'max:'.PresensiMessages::MAX_FOTO_BASE64, 'regex:/^data:image\/\w+;base64,/'],
            'keterangan' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
        ]);

        return DB::transaction(function () use ($request, $presensi, $pegawai) {
            $locked = Presensi::lockForUpdate()->findOrFail($presensi->id);

            $overlayData = [
                'label' => 'BUKTI KEGIATAN',
                'pegawai' => $pegawai->nama_lengkap,
                'unit' => $locked->unitSekolah?->nama ?? '',
                'time' => Carbon::now()->format('H:i:s').' WIB',
                'date' => Carbon::now()->locale('id')->isoFormat('dddd, D MMMM YYYY'),
                'coordinates' => ($request->latitude && $request->longitude)
                    ? number_format((float) $request->latitude, 6).', '.number_format((float) $request->longitude, 6)
                    : null,
                'accuracy' => $request->accuracy ? number_format((float) $request->accuracy, 0).'m' : null,
                'latitude' => $request->latitude ? (float) $request->latitude : null,
                'longitude' => $request->longitude ? (float) $request->longitude : null,
            ];
            $geo = app(GeocodingService::class)->reverse($request->latitude, $request->longitude);
            $overlayData['kecamatan'] = $geo['kecamatan'] ?? null;
            $overlayData['kelurahan'] = $geo['kelurahan'] ?? null;
            $overlayData['kabupaten'] = $geo['kabupaten'] ?? null;

            $path = app(ImageUploadService::class)->storeBase64(
                $request->foto,
                'presensi/tugas-luar',
                $overlayData,
                PresensiMessages::MAX_FOTO_BYTES,
                ['id' => $pegawai->id, 'nama' => $pegawai->nama_lengkap]
            );

            $bukti = $locked->foto_kegiatan ?? [];
            if (! is_array($bukti)) {
                $bukti = [];
            }
            $bukti[] = [
                'path' => $path,
                'keterangan' => $request->keterangan,
                'latitude' => $request->latitude !== null ? (float) $request->latitude : null,
                'longitude' => $request->longitude !== null ? (float) $request->longitude : null,
                'accuracy' => $request->accuracy !== null ? (float) $request->accuracy : null,
                'created_at' => Carbon::now()->toDateTimeString(),
            ];
            $locked->foto_kegiatan = $bukti;
            $locked->save();

            return response()->json([
                'success' => true,
                'message' => 'Foto kegiatan tersimpan.',
                'foto_kegiatan_urls' => $locked->foto_kegiatan_urls,
            ]);
        });
    }

    public function audit($id)
    {
        if (! auth()->user()?->can('manage_master_data')) {
            abort(403);
        }

        $user = auth()->user();
        $presensi = Presensi::with('pegawai:id,nama_lengkap')->findOrFail($id);

        // Scope unit untuk admin unit (anti-IDOR), konsisten dengan reviewDetail
        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
            if (! $presensi->pegawai?->belongsToUnit($user->unit_sekolah_id)) {
                abort(403, 'Akses ditolak.');
            }
        }

        $audits = AuditPresensi::where('presensi_id', $id)
            ->with('user:id,name')
            ->latest()
            ->get(['id', 'user_id', 'aksi', 'field', 'nilai_lama', 'nilai_baru', 'created_at']);

        return response()->json([
            'audits' => $audits,
            'presensi' => [
                'pegawai_nama' => $presensi->pegawai?->nama_lengkap,
                'tanggal' => $presensi->tanggal?->format('Y-m-d'),
                'status' => $presensi->status,
                'foto_masuk_url' => $presensi->foto_masuk_url,
                'foto_keluar_url' => $presensi->foto_keluar_url,
                'foto_masuk_status' => $presensi->foto_masuk_status,
                'foto_keluar_status' => $presensi->foto_keluar_status,
                'foto_masuk_error' => $presensi->foto_masuk_error,
                'foto_keluar_error' => $presensi->foto_keluar_error,
                'foto_kegiatan_urls' => $presensi->foto_kegiatan_urls,
            ],
        ]);
    }

    /**
     * Detail anti-spoof untuk review admin (trajectory, motion, IP geo, EXIF).
     * Menampilkan alasan kenapa record di-flag (fitur anti-spoof v2).
     */
    public function reviewDetail($id)
    {
        if (! auth()->user()?->can('view_presensi')) {
            abort(403);
        }

        $user = auth()->user();
        $presensi = Presensi::with('pegawai:id,nama_lengkap')->findOrFail($id);

        // Scope unit untuk admin unit
        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
            if (! $presensi->pegawai?->belongsToUnit($user->unit_sekolah_id)) {
                abort(403, 'Akses ditolak.');
            }
        }

        $trajectory = $presensi->trajectory_samples;
        $motion = $presensi->motion_samples;
        $motionVariance = null;
        if (is_array($motion) && count($motion) >= 2) {
            $totals = array_map(function ($s) {
                return sqrt((float) ($s['x'] ?? 0) ** 2 + (float) ($s['y'] ?? 0) ** 2 + (float) ($s['z'] ?? 0) ** 2);
            }, array_values($motion));
            $mean = array_sum($totals) / count($totals);
            $motionVariance = round(array_sum(array_map(fn ($v) => ($v - $mean) ** 2, $totals)) / count($totals), 6);
        }

        // Ringkas trajectory untuk tampilan (hapus data mentah berat)
        $trajectorySummary = null;
        if (is_array($trajectory)) {
            $trajectorySummary = array_map(function ($p) {
                return [
                    'label' => $p['label'] ?? null,
                    'lat' => $p['lat'] ?? null,
                    'lng' => $p['lng'] ?? null,
                    'accuracy' => $p['accuracy'] ?? null,
                    'captured_at' => $p['captured_at'] ?? null,
                ];
            }, $trajectory);
        }

        return response()->json([
            'presensi' => [
                'id' => $presensi->id,
                'pegawai_nama' => $presensi->pegawai?->nama_lengkap,
                'tanggal' => $presensi->tanggal?->format('Y-m-d'),
                'status' => $presensi->status,
                'latitude_masuk' => $presensi->latitude_masuk,
                'longitude_masuk' => $presensi->longitude_masuk,
                'akurasi_masuk' => $presensi->akurasi_masuk,
                'kecepatan_masuk' => $presensi->kecepatan_masuk,
                'captured_at' => $presensi->captured_at?->toIso8601String(),
                'foto_masuk_url' => $presensi->foto_masuk_url,
                'foto_keluar_url' => $presensi->foto_keluar_url,
                'foto_masuk_status' => $presensi->foto_masuk_status,
                'foto_keluar_status' => $presensi->foto_keluar_status,
                'foto_masuk_error' => $presensi->foto_masuk_error,
                'foto_keluar_error' => $presensi->foto_keluar_error,
                'lokasi_perlu_review' => $presensi->lokasi_perlu_review,
                'posisi_mencurigakan' => $presensi->posisi_mencurigakan,
                'motion_suspect' => $presensi->motion_suspect,
                'trajectory' => $trajectorySummary,
                'motion_sample_count' => is_array($motion) ? count($motion) : 0,
                'motion_variance' => $motionVariance,
                'ip_geo' => $presensi->ip_geo,
                'exif_meta' => $presensi->exif_meta,
                'foto_kegiatan_urls' => $presensi->foto_kegiatan_urls,
                // Dinas luar fields
                'is_tugas_luar' => $presensi->is_tugas_luar,
                'tujuan' => $presensi->tujuan,
                'keterangan' => $presensi->keterangan,
                'tugas_luar_status' => $presensi->tugas_luar_status,
                'foto_kegiatan' => $presensi->foto_kegiatan,
            ],
        ]);
    }
}
