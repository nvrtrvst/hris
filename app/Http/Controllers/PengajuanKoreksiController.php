<?php

namespace App\Http\Controllers;

use App\Helpers\ApprovalHelper;
use App\Helpers\DownloadFilename;
use App\Helpers\NotificationHelper;
use App\Helpers\PayrollLockHelper;
use App\Http\Requests\KoreksiJpRequest;
use App\Models\AuditPresensi;
use App\Models\Jadwal;
use App\Models\PengajuanKoreksi;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Models\User;
use App\Notifications\PresensiDikoreksi;
use App\Notifications\StatusKoreksi;
use App\Services\JpTerlewatService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PengajuanKoreksiController extends Controller
{
    public function index(Request $request, JpTerlewatService $jpTerlewat)
    {
        $user = auth()->user();
        $canViewPengajuan = $user && ($user->can('view_izin') || $user->isApprover());
        $canKoreksiJp = $user && $user->can('koreksi_presensi');

        if (! $canViewPengajuan && ! $canKoreksiJp) {
            abort(403, 'Akses ditolak.');
        }

        $request->validate([
            'tab' => 'nullable|in:pengajuan,jp',
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d',
            'unit_id' => 'nullable|integer|exists:unit_sekolah,id',
            'search' => 'nullable|string|max:100',
        ]);

        $tab = $request->input('tab', $canViewPengajuan ? 'pengajuan' : 'jp');
        if ($tab === 'jp' && ! $canKoreksiJp) {
            $tab = $canViewPengajuan ? 'pengajuan' : 'jp';
        }
        if ($tab === 'pengajuan' && ! $canViewPengajuan) {
            $tab = 'jp';
        }

        if ($tab === 'jp' && ! $user->can('view_all_units') && ! $user->unit_sekolah_id) {
            abort(403, 'Akses unit diperlukan untuk melihat JP terlewat.');
        }

        $pengajuans = new LengthAwarePaginator([], 0, 10, 1, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);
        $stats = ['total' => 0, 'pending' => 0, 'selesai' => 0];

        if ($canViewPengajuan && $tab === 'pengajuan') {
            $query = PengajuanKoreksi::with([
                'pegawai' => fn ($q) => $q->select('id', 'user_id', 'nama_lengkap', 'nuptk'),
                'presensi' => fn ($q) => $q->select('id', 'pegawai_id', 'tanggal', 'jam_masuk', 'jam_keluar', 'unit_sekolah_id'),
                'presensi.unitSekolah' => fn ($q) => $q->select('id', 'nama'),
                'approver:id,name',
                'rejectedByUser:id,name',
            ]);

            if ($user->unit_sekolah_id && ! $user->can('view_all_units')) {
                $query->whereHas('pegawai', fn ($q) => $q->forUnit($user->unit_sekolah_id));
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->whereHas('pegawai', fn ($q) => $q->where('nama_lengkap', 'like', "%{$search}%"));
            }

            if ($request->filled('status') && $request->status !== 'semua') {
                $query->where('status', $request->status);
            }

            $stats = [
                'total' => (clone $query)->count(),
                'pending' => (clone $query)->where('status', 'pending')->count(),
                'selesai' => (clone $query)->whereIn('status', ['disetujui', 'ditolak'])->count(),
            ];

            $pengajuans = $query->orderBy('created_at', 'desc')->paginate(10)->withQueryString();

            $pengajuans->getCollection()->transform(function ($item) use ($user) {
                $item->can_act = $this->canDecide($user, $item);

                return $item;
            });
        }

        $start = Carbon::parse($request->input('start_date') ?: Carbon::today()->subDays(6)->toDateString())->startOfDay();
        $end = Carbon::parse($request->input('end_date') ?: Carbon::today()->toDateString())->startOfDay();
        $jpFilters = [
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'unit_id' => $request->input('unit_id'),
            'search' => $request->input('search'),
        ];
        $jpStats = ['total' => 0, 'belum_presensi' => 0, 'alpa_otomatis' => 0, 'alpa_manual' => 0];
        $jpTerlewatPage = new LengthAwarePaginator([], 0, 20, 1, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        if ($tab === 'jp') {
            if ($start->gt($end)) {
                throw ValidationException::withMessages(['end_date' => 'Tanggal akhir harus setelah tanggal mulai.']);
            }
            if ($start->diffInDays($end) > 31) {
                throw ValidationException::withMessages(['end_date' => 'Rentang maksimal 31 hari.']);
            }

            $data = $jpTerlewat->build(
                $user,
                $start,
                $end,
                $request->filled('unit_id') ? (int) $request->input('unit_id') : null,
                $request->input('search')
            );

            $page = max((int) $request->input('page', 1), 1);
            $perPage = 20;
            $jpTerlewatPage = new LengthAwarePaginator(
                $data['rows']->forPage($page, $perPage)->values(),
                $data['rows']->count(),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
            $jpStats = $data['stats'];
        }

        return Inertia::render('Koreksi/Index', [
            'pengajuans' => $pengajuans,
            'filters' => [
                'search' => $request->input('search'),
                'status' => $request->input('status', 'semua'),
                'tab' => $tab,
            ],
            'stats' => $stats,
            'tab' => $tab,
            'can_view_pengajuan' => $canViewPengajuan,
            'can_koreksi_jp' => $canKoreksiJp,
            'jp_terlewat' => $jpTerlewatPage,
            'jp_filters' => $jpFilters,
            'jp_stats' => $jpStats,
            'units' => $user->can('view_all_units') ? UnitSekolah::orderBy('nama')->get(['id', 'nama']) : [],
        ]);
    }

    public function koreksiJp(KoreksiJpRequest $request)
    {
        $user = auth()->user();
        $validated = $request->validated();

        $jadwal = Jadwal::with([
            'pegawai:id,user_id,nama_lengkap,status_aktif',
            'pegawai.user:id,name,email',
            'unitSekolah:id,nama,singkatan',
            'pegawaiMapel.mataPelajaran:id,nama',
        ])->findOrFail($validated['jadwal_id']);

        if ($jadwal->jenis_jadwal !== 'mengajar' || ! $jadwal->unit_sekolah_id) {
            throw ValidationException::withMessages(['jadwal_id' => 'Jadwal bukan sesi mengajar yang valid.']);
        }

        if (! $user->can('view_all_units')) {
            if (! $user->unit_sekolah_id
                || (int) $jadwal->unit_sekolah_id !== (int) $user->unit_sekolah_id
                || ! $jadwal->pegawai?->belongsToUnit($user->unit_sekolah_id)) {
                abort(403, 'Akses ditolak.');
            }
        }

        $tanggal = Carbon::parse($validated['tanggal'])->startOfDay();
        $hari = $tanggal->locale('id')->translatedFormat('l');

        if ($jadwal->hari !== $hari) {
            throw ValidationException::withMessages(['tanggal' => 'Tanggal tidak sesuai hari jadwal.']);
        }

        if (Carbon::parse($tanggal->toDateString().' '.$jadwal->jam_selesai)->isFuture()) {
            throw ValidationException::withMessages(['jadwal_id' => 'JP belum selesai.']);
        }

        if (PayrollLockHelper::isPeriodLocked($jadwal->pegawai_id, $tanggal)) {
            throw ValidationException::withMessages(['jadwal_id' => 'Periode penggajian sudah dikunci.']);
        }

        $jamMasuk = Carbon::parse($validated['jam_masuk'])->format('H:i:s');
        $jamKeluar = Carbon::parse($validated['jam_keluar'])->format('H:i:s');
        $alasan = $validated['alasan'];

        try {
            [$presensi, $statusLama] = DB::transaction(function () use ($jadwal, $tanggal, $validated, $jamMasuk, $jamKeluar, $alasan) {
                $presensi = Presensi::where('pegawai_id', $jadwal->pegawai_id)
                    ->where('jadwal_id', $jadwal->id)
                    ->whereDate('tanggal', $tanggal->toDateString())
                    ->lockForUpdate()
                    ->first();

                if ($presensi && ($presensi->is_lembur || $presensi->is_tugas_luar)) {
                    throw ValidationException::withMessages(['jadwal_id' => 'JP ini bukan presensi mengajar biasa.']);
                }

                if ($presensi && $presensi->status !== 'alpa') {
                    throw ValidationException::withMessages(['jadwal_id' => 'JP ini sudah memiliki presensi.']);
                }

                $statusLama = $presensi?->status ?? 'belum_presensi';
                $jamMasukLama = $presensi?->jam_masuk;
                $jamKeluarLama = $presensi?->jam_keluar;

                $presensi ??= new Presensi([
                    'pegawai_id' => $jadwal->pegawai_id,
                    'jadwal_id' => $jadwal->id,
                    'tanggal' => $tanggal->toDateString(),
                ]);

                $presensi->forceFill([
                    'unit_sekolah_id' => $jadwal->unit_sekolah_id,
                    'tipe_presensi' => 'mengajar',
                    'tanggal' => $tanggal->toDateString(),
                    'is_lembur' => false,
                    'lembur_status' => null,
                    'is_tugas_luar' => false,
                    'tugas_luar_status' => null,
                    'tugas_luar_id' => null,
                    'jam_masuk' => $jamMasuk,
                    'jam_keluar' => $jamKeluar,
                    'status' => $validated['status'],
                    'persentase_bayar_jam' => $presensi->persentase_bayar_jam ?? 100,
                    'keterangan' => trim(($presensi->keterangan ? $presensi->keterangan.' ' : '').'[Koreksi JP: '.$alasan.']'),
                ])->save();

                AuditPresensi::log($presensi->id, 'koreksi_jp', 'status', $statusLama, $validated['status'], $alasan);

                if ((string) $jamMasukLama !== $jamMasuk) {
                    AuditPresensi::log($presensi->id, 'koreksi_jp', 'jam_masuk', $jamMasukLama, $jamMasuk, $alasan);
                }
                if ((string) $jamKeluarLama !== $jamKeluar) {
                    AuditPresensi::log($presensi->id, 'koreksi_jp', 'jam_keluar', $jamKeluarLama, $jamKeluar, $alasan);
                }

                return [$presensi, $statusLama];
            });
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                throw ValidationException::withMessages(['jadwal_id' => 'JP ini sudah memiliki presensi.']);
            }

            throw $e;
        }

        $presensi->loadMissing(['pegawai.user', 'jadwal.pegawaiMapel.mataPelajaran']);
        $target = $presensi->pegawai?->user;

        if ($target?->email) {
            NotificationHelper::sendSafely(
                $target,
                new PresensiDikoreksi($presensi, $statusLama, $alasan, $user)
            );
        }

        return back()->with('message', 'Presensi mengajar berhasil dikoreksi.');
    }

    public function approve(Request $request, $id)
    {
        $user = auth()->user();
        if (! $user || (! $user->can('view_izin') && ! $user->isApprover())) {
            abort(403, 'Akses ditolak.');
        }

        $request->validate([
            'catatan_approval' => 'nullable|string|max:500',
        ]);

        $koreksi = DB::transaction(function () use ($id, $user, $request) {
            $koreksi = PengajuanKoreksi::with('pegawai')->lockForUpdate()->findOrFail($id);

            if ($user->unit_sekolah_id && ! $user->can('view_all_units')) {
                if (! $koreksi->pegawai->belongsToUnit($user->unit_sekolah_id)) {
                    abort(403, 'Akses ditolak.');
                }
            }

            if (! $this->canDecide($user, $koreksi)) {
                abort(403, 'Anda tidak berhak menyetujui pengajuan ini.');
            }

            if ($koreksi->status !== 'pending') {
                throw ValidationException::withMessages(['error' => 'Pengajuan sudah final.']);
            }

            $presensi = Presensi::lockForUpdate()->findOrFail($koreksi->presensi_id);
            $nilaiLama = $presensi->jam_keluar;
            // NilaiBaru format H:i (input); samakan format kolom time HH:MM:SS.
            $presensi->jam_keluar = date('H:i:s', strtotime($koreksi->nilai_baru));
            $presensi->save();

            $koreksi->status = 'disetujui';
            $koreksi->approver_id = $user->id;
            $koreksi->approved_at = now();
            if ($request->filled('catatan_approval')) {
                $koreksi->catatan_approval = $request->catatan_approval;
            }
            $koreksi->save();

            AuditPresensi::log($presensi->id, 'koreksi_jam_keluar', 'jam_keluar', $nilaiLama, $koreksi->nilai_baru,
                "Koreksi {$koreksi->nomor} — {$koreksi->alasan}"
            );

            return $koreksi;
        });

        NotificationHelper::sendSafely($koreksi->pegawai?->user, new StatusKoreksi($koreksi, 'disetujui'));

        return back()->with('message', 'Koreksi disetujui — jam keluar presensi telah diperbarui.');
    }

    public function reject(Request $request, $id)
    {
        $user = auth()->user();
        if (! $user || (! $user->can('view_izin') && ! $user->isApprover())) {
            abort(403, 'Akses ditolak.');
        }

        $request->validate([
            'alasan_penolakan' => 'required|string|max:255',
        ]);

        $koreksi = DB::transaction(function () use ($id, $user, $request) {
            $koreksi = PengajuanKoreksi::with('pegawai')->lockForUpdate()->findOrFail($id);

            if ($user->unit_sekolah_id && ! $user->can('view_all_units')) {
                if (! $koreksi->pegawai->belongsToUnit($user->unit_sekolah_id)) {
                    abort(403, 'Akses ditolak.');
                }
            }

            if (! $this->canDecide($user, $koreksi)) {
                abort(403, 'Anda tidak berhak menolak pengajuan ini.');
            }

            if ($koreksi->status !== 'pending') {
                throw ValidationException::withMessages(['error' => 'Pengajuan sudah final.']);
            }

            $koreksi->status = 'ditolak';
            $koreksi->rejected_by = $user->id;
            $koreksi->rejected_at = now();
            $koreksi->alasan_penolakan = $request->alasan_penolakan;
            $koreksi->save();

            return $koreksi;
        });

        NotificationHelper::sendSafely($koreksi->pegawai?->user,
            new StatusKoreksi($koreksi, 'ditolak', $request->alasan_penolakan));

        return back()->with('message', 'Pengajuan koreksi ditolak.');
    }

    /**
     * Cetak formulir (1 pengajuan = 1 lembar A4). Dipakai route desktop DAN
     * mobile (guard-agnostic): authorization cek user terhadap pengajuan.
     */
    public function print(Request $request, $id)
    {
        $user = auth()->user();
        abort_unless($user, 401);

        $koreksi = PengajuanKoreksi::with([
            'pegawai.units',
            'presensi.unitSekolah',
            'approver:id,name',
            'rejectedByUser:id,name',
        ])->findOrFail($id);

        $unitId = optional($koreksi->pegawai->units()->wherePivot('is_primary', true)->first()
            ?? $koreksi->pegawai->units()->first())?->id;

        $isOwner = $koreksi->pegawai?->user_id === $user->id;
        $isStaff = $user->hasRole('superadmin') || $user->can('view_presensi')
            || ApprovalHelper::isUnitHead($user, $unitId);
        abort_unless($isOwner || $isStaff, 403, 'Akses ditolak.');

        $kopUnit = $koreksi->presensi?->unitSekolah;
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

        $pdf = Pdf::loadView('exports.pdf-koreksi-presensi', [
            'koreksi' => $koreksi,
            'kop' => $kop,
            'logoPath' => $logoPath,
            'logoWidth' => $logoWidth,
        ])->setPaper('A4', 'portrait');

        // Nomor berisi '/' — tidak sah sebagai nama file HTTP disposition.
        $nama = DownloadFilename::person('Koreksi_Presensi', $koreksi->pegawai, $koreksi->nomor, 'pdf');

        // unduh=1 -> attachment (mobile: file masuk Downloads). Tanpa itu tetap inline untuk cetak.
        return $request->boolean('unduh') ? $pdf->download($nama) : $pdf->stream($nama);
    }

    private function canDecide(User $user, PengajuanKoreksi $koreksi): bool
    {
        if ($user->hasRole('superadmin')) {
            return true;
        }

        if ($koreksi->approver_id === $user->id) {
            return true;
        }

        $unitId = optional($koreksi->pegawai->units()->wherePivot('is_primary', true)->first()
            ?? $koreksi->pegawai->units()->first())?->id;

        return ApprovalHelper::isUnitHead($user, $unitId);
    }

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
}
