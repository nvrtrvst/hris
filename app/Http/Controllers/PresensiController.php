<?php

namespace App\Http\Controllers;

use App\Constants\PresensiMessages;
use App\Helpers\PayrollLockHelper;
use App\Http\Controllers\Concerns\ScopesPimpinan;
use App\Jobs\ProcessPresensiFoto;
use App\Models\AuditPresensi;
use App\Models\Jadwal;
use App\Models\Pegawai;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Services\GeocodingService;
use App\Services\ImageUploadService;
use App\Traits\CalculatesDistance;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Response;

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
        ]);

        $user = auth()->user();
        $isAdmin = $user && $user->can('view_presensi');
        $query = Presensi::with(['unitSekolah', 'pegawai', 'jadwal.pegawaiMapel']);

        if ($this->isPimpinanReadOnly($user)) {
            if ($this->isKepsek($user)) {
                // Kepsek: default semua presensi dalam unit, atau presensi sendiri.
                if ($request->view_mode === 'self') {
                    $query->where('pegawai_id', $user->pegawai?->id);
                } else {
                    $unitId = $user->unit_sekolah_id
                        ?? $user->pegawai?->units->first()?->id;
                    if ($unitId) {
                        $query->where('unit_sekolah_id', $unitId);
                    } else {
                        $query->whereRaw('1 = 0');
                    }
                }
            } else {
                // Pimpinan lain (kepala TU/ketua yayasan): HANYA bawahan langsung.
                $this->scopePimpinanBawahan($query, $user);
            }
        } elseif (! $isAdmin) {
            $pegawai = Pegawai::where('user_id', auth()->id())->first();
            if ($pegawai) {
                $query->where('pegawai_id', $pegawai->id);
            } else {
                $query->where('id', -1);
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
        $stats = $this->presensiStats((clone $query));

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
            $query->where('lokasi_perlu_review', true);
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
            return $this->handleBelumPresensi($request, $user, $stats);
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
        // Ringkas view: kirim semua data tanpa pagination (buildGroups butuh semua records per orang).
        if ($request->input('display_mode') === 'ringkas') {
            $presensis = $query
                ->orderBy('tanggal', 'desc')
                ->orderBy('jam_masuk', 'asc')
                ->orderBy('id', 'asc')
                ->get();
            $presensis->load('pegawai.jabatans');
            // Wrap in LengthAwarePaginator agar frontend tetap bisa akses .data dan .links
            $presensis = new LengthAwarePaginator(
                $presensis, $presensis->count(), $presensis->count(), 1, ['path' => $request->url()]
            );
        } else {
            $presensis = $query
                ->orderBy('tanggal', 'desc')
                ->orderBy('jam_masuk', 'asc')
                ->orderBy('id', 'asc')
                ->paginate($perPage)
                ->withQueryString();
            $presensis->load('pegawai.jabatans');
        }

        $units = [];
        if ($user->can('view_all_units')) {
            $units = UnitSekolah::orderBy('nama')->get();
        }

        return inertia('Presensi/Index', [
            'presensis' => $presensis,
            'pegawai' => $isAdmin ? null : ($pegawai ?? null),
            'filters' => $request->only(['start_date', 'end_date', 'unit_id', 'lembur_filter', 'lokasi_filter', 'suspicious_filter', 'status_filter', 'jadwal_filter', 'jenis_filter', 'search', 'view_mode']),
            'units' => $units,
            'userRole' => $user->roles->first()?->name ?? 'pegawai',
            'stats' => $stats,
        ]);
    }

    /**
     * Ringkasan presensi untuk kartu statistik halaman index.
     * Satu query agregat (group by status) + dua count ringan.
     */
    private function presensiStats($query): array
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
    private function countActivePegawaiInScope($presensiQuery): int
    {
        $user = auth()->user();
        $pegawais = Pegawai::where('status_aktif', 'aktif');

        // Terapkan scope unit yang sama dengan index()
        if ($user->can('view_all_units')) {
            // Semua unit — tidak perlu filter
        } elseif ($user->unit_sekolah_id) {
            $pegawais->forUnit($user->unit_sekolah_id);
        }

        return $pegawais->count();
    }

    /**
     * Handle filter "belum_presensi": tampilkan pegawai aktif yang TIDAK punya
     * record presensi di rentang tanggal yang difilter.
     * Return presensi-shaped collection supaya frontend kompatibel.
     */
    private function handleBelumPresensi(Request $request, $user, array $stats): Response
    {
        $startDate = $request->start_date ?? Carbon::today()->toDateString();
        $endDate = $request->end_date ?? Carbon::today()->toDateString();

        $pegawaisWithPresensi = Presensi::whereBetween('tanggal', [$startDate, $endDate])
            ->distinct()->pluck('pegawai_id');

        $pegawais = Pegawai::where('status_aktif', 'aktif')
            ->with(['units', 'jabatans'])
            ->whereNotIn('id', $pegawaisWithPresensi);

        // Terapkan scope unit yang sama
        if ($user->can('view_all_units')) {
            // Semua unit
        } elseif ($user->unit_sekolah_id) {
            $pegawais->forUnit($user->unit_sekolah_id);
        } elseif (! $user->can('view_presensi')) {
            // Pegawai biasa: hanya diri sendiri
            $selfPegawai = Pegawai::where('user_id', auth()->id())->first();
            if ($selfPegawai) {
                $pegawais->where('id', $selfPegawai->id);
            } else {
                $pegawais->where('id', -1);
            }
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
            'pegawai' => null,
            'filters' => $request->only(['start_date', 'end_date', 'unit_id', 'lembur_filter', 'lokasi_filter', 'suspicious_filter', 'status_filter', 'jadwal_filter', 'jenis_filter', 'search', 'view_mode']),
            'units' => $units,
            'userRole' => $user->roles->first()?->name ?? 'pegawai',
            'stats' => $stats,
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
            'nip' => $p->nip ?? '-',
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

        // Blokir presensi di hari Minggu atau Sabtu (jika unit tidak beroperasi)
        $hariMap = ['Sunday' => 'Minggu', 'Saturday' => 'Sabtu'];
        $hariIni = $hariMap[Carbon::now()->format('l')] ?? null;
        if ($hariIni === 'Minggu') {
            return back()->withErrors(['jadwal_id' => sprintf(PresensiMessages::HARI_TIDAK_AKTIF, 'Minggu')]);
        }
        if ($hariIni === 'Sabtu' && ! $unit->jam_kerja_sabtu_mulai) {
            return back()->withErrors(['jadwal_id' => sprintf(PresensiMessages::HARI_TIDAK_AKTIF, 'Sabtu')]);
        }

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
            $presensi = Presensi::with('pegawai')
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

            $presensi->update(['lembur_status' => 'disetujui']);

            AuditPresensi::log($presensi->id, 'approve_lembur', 'lembur_status', 'pending', 'disetujui');
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
            $presensi = Presensi::with('pegawai')
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

            $presensi->update(['lembur_status' => 'ditolak']);

            AuditPresensi::log($presensi->id, 'reject_lembur', 'lembur_status', 'pending', 'ditolak');
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
