<?php

namespace App\Http\Controllers;

use App\Constants\PresensiMessages;
use App\Jobs\ProcessPresensiFoto;
use App\Models\Announcement;
use App\Models\Jadwal;
use App\Models\Presensi;
use App\Services\AttestationService;
use App\Services\SpoofDetector;
use App\Traits\CalculatesDistance;
use App\Traits\ResolvesPegawai;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MobileController extends Controller
{
    use CalculatesDistance, ResolvesPegawai;

    public function __construct(
        private AttestationService $attestationService,
    ) {}

    private function rememberJadwal(string $key, int $ttl, \Closure $callback): mixed
    {
        try {
            $cached = Cache::remember($key, $ttl, $callback);
            if ($cached instanceof \__PHP_Incomplete_Class) {
                throw new \UnexpectedValueException('Cache corrupted: incomplete class');
            }

            return $cached;
        } catch (\Throwable $e) {
            Log::warning('Cache jadwal corrupted', ['key' => $key, 'error' => $e->getMessage()]);
            Cache::forget($key);

            return $callback();
        }
    }

    public function dashboard()
    {
        $pegawai = $this->getPegawai();
        $pegawai->load('units');
        $hariMap = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
        $hariIniIndo = $hariMap[Carbon::now()->format('l')];

        $presensiHariIni = Presensi::with('unitSekolah')->where('pegawai_id', $pegawai->id)
            ->where('tanggal', Carbon::today()->toDateString())
            ->first();

        $presensiTerbaru = Presensi::with('unitSekolah')
            ->where('pegawai_id', $pegawai->id)
            ->where('tanggal', '>=', Carbon::today()->subDays(3)->toDateString())
            ->where('tanggal', '<=', Carbon::today()->toDateString())
            ->orderBy('tanggal', 'desc')
            ->get();

        $jadwalsHariIni = $this->rememberJadwal('mobile.jadwal.'.$pegawai->id.'.'.$hariIniIndo, 900, function () use ($pegawai, $hariIniIndo) {
            return Jadwal::with(['unitSekolah', 'mataPelajaran'])
                ->where('pegawai_id', $pegawai->id)
                ->where('hari', $hariIniIndo)
                ->orderBy('jam_mulai', 'asc')
                ->get();
        });

        return inertia('Mobile/Dashboard', [
            'pegawai' => $pegawai,
            'presensi' => $presensiHariIni,
            'presensiSeminggu' => $presensiTerbaru,
            'jadwalsHariIni' => $jadwalsHariIni,
        ]);
    }

    public function riwayat(Request $request)
    {
        $validated = $request->validate([
            'bulan' => 'nullable|integer|between:1,12',
            'tahun' => 'nullable|integer|between:2020,2100',
        ]);
        $pegawai = $this->getPegawai();

        $bulan = (int) ($validated['bulan'] ?? Carbon::now()->month);
        $tahun = (int) ($validated['tahun'] ?? Carbon::now()->year);

        $presensi = Presensi::with(['unitSekolah', 'jadwal.mataPelajaran'])
            ->where('pegawai_id', $pegawai->id)
            ->whereBetween('tanggal', [
                Carbon::createFromDate($tahun, $bulan, 1)->startOfMonth()->format('Y-m-d'),
                Carbon::createFromDate($tahun, $bulan, 1)->endOfMonth()->format('Y-m-d'),
            ])
            ->orderBy('tanggal', 'desc')
            ->get();

        // Ringkasan kehadiran bulan ini (F5)
        $countsCount = $presensi->filter(fn ($p) => ! $p->is_lembur)->groupBy('status')->map->count();
        $hadir = (int) ($countsCount['hadir'] ?? 0);
        $telat = (int) ($countsCount['telat'] ?? 0);
        $sakit = (int) ($countsCount['sakit'] ?? 0);
        $izin = (int) ($countsCount['izin'] ?? 0);
        $cuti = (int) ($countsCount['cuti'] ?? 0);
        $alpa = (int) ($countsCount['alpa'] ?? 0);
        $awalBulan = Carbon::createFromDate($tahun, $bulan, 1);
        $workingDays = $this->countWeekdaysInRange($awalBulan->copy()->startOfMonth(), $awalBulan->copy()->endOfMonth());
        $totalHadir = $hadir + $telat + $izin + $sakit + $cuti;
        $alphaTerisi = max(0, $workingDays - $totalHadir);
        $alpa = $alpa + $alphaTerisi;
        $presentCount = $hadir + $telat;
        $percent = $workingDays > 0 ? round(($presentCount / $workingDays) * 100) : 0;

        $summary = [
            'hadir' => $hadir,
            'telat' => $telat,
            'sakit' => $sakit,
            'izin' => $izin,
            'cuti' => $cuti,
            'alpa' => $alpa,
            'working_days' => $workingDays,
            'present' => $presentCount,
            'percent' => $percent,
        ];

        return inertia('Mobile/Riwayat', [
            'pegawai' => $pegawai,
            'presensi' => $presensi,
            'summary' => $summary,
            'filters' => ['bulan' => $bulan, 'tahun' => $tahun],
        ]);
    }

    /**
     * Hitung hari kerja (Senin-Jumat) dalam rentang tanggal (F5).
     * O(1): formula matematis, bukan loop harian.
     * Catatan: hari libur nasional belum di-exclude — enhancement nanti.
     */
    private function countWeekdaysInRange(Carbon $start, Carbon $end): int
    {
        if ($start->gt($end)) {
            return 0;
        }

        $totalDays = $start->diffInDays($end) + 1;
        $fullWeeks = intdiv($totalDays, 7);
        $remainderDays = $totalDays % 7;
        $startDay = $start->dayOfWeek; // 0 = Minggu

        $count = $fullWeeks * 5;
        for ($i = 0; $i < $remainderDays; $i++) {
            $day = ($startDay + $i) % 7;
            if ($day !== Carbon::SUNDAY && $day !== Carbon::SATURDAY) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Daftar pengumuman yayasan untuk portal mobile (F6).
     */
    public function pengumuman()
    {
        $pegawai = $this->getPegawai();
        $unitId = $pegawai?->units()->first()?->id;

        $pengumuman = Announcement::with('creator:id,name')
            ->published()
            ->forUnit($unitId)
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->get();

        // Semua pengumuman yang tampil otomatis ditandai terbaca (1 query upsert,
        // tanpa N+1) — badge selanjutnya hanya menghitung yang belum dibaca.
        if ($pegawai && $pengumuman->isNotEmpty()) {
            Announcement::markReadBatch($pengumuman->pluck('id')->all(), $pegawai);
        }

        return inertia('Mobile/Pengumuman', [
            'pengumuman' => $pengumuman,
        ]);
    }

    public function jadwal()
    {
        $pegawai = $this->getPegawai();

        $jadwals = $this->rememberJadwal('mobile.jadwal.'.$pegawai->id, 900, function () use ($pegawai) {
            return Jadwal::with(['unitSekolah', 'mataPelajaran'])
                ->where('pegawai_id', $pegawai->id)
                ->orderByRaw("CASE hari WHEN 'Senin' THEN 1 WHEN 'Selasa' THEN 2 WHEN 'Rabu' THEN 3 WHEN 'Kamis' THEN 4 WHEN 'Jumat' THEN 5 WHEN 'Sabtu' THEN 6 WHEN 'Minggu' THEN 7 END")
                ->orderBy('jam_mulai', 'asc')
                ->get()
                ->groupBy('hari');
        });

        return inertia('Mobile/Jadwal', [
            'pegawai' => $pegawai,
            'jadwalPerHari' => $jadwals,
        ]);
    }

    public function kelasUnit(Request $request)
    {
        $validated = $request->validate([
            'jadwal_id' => 'required|integer|min:1',
        ]);
        [$jadwal, $unit] = $this->resolveOwnedJadwal((int) $validated['jadwal_id']);

        if ($jadwal->kelas_label) {
            return response()->json(['success' => true, 'kelas' => []])->header('Cache-Control', 'no-store');
        }

        $response = $this->keuanganRequest('kelas-by-unit', [
            'unit' => $unit->nama ?: $unit->singkatan,
        ]);

        if (! $response?->successful()) {
            return response()->json(['success' => false, 'kelas' => [], 'message' => 'Data kelas belum tersedia.'], 502)->header('Cache-Control', 'no-store');
        }

        return response()->json([
            'success' => true,
            'school_name' => $response->json('data.schoolName'),
            'kelas' => $response->json('data.classes') ?? [],
        ])->header('Cache-Control', 'no-store');
    }

    // Proxy ke API internal app keuangan untuk mengambil daftar siswa per kelas.
    // Kunci API disimpan server-side (tidak dikirim ke browser).
    public function siswaKelas(Request $request)
    {
        $validated = $request->validate($this->studentClassRules());
        [$jadwal, $unit] = $this->resolveOwnedJadwal((int) $validated['jadwal_id']);
        $class = $this->resolveClassPayload($jadwal, $validated);

        $response = $this->keuanganRequest('siswa-by-class', [
            'unit' => $unit->nama ?: $unit->singkatan,
            ...$class,
        ]);

        if (! $response?->successful()) {
            return response()->json(['success' => false, 'siswa' => [], 'message' => 'Data siswa belum tersedia.'], 502)->header('Cache-Control', 'no-store');
        }

        $data = $response->json('data') ?? [];

        return response()->json([
            'success' => true,
            'school_name' => $data['schoolName'] ?? null,
            'class_labels' => $data['classLabels'] ?? [],
            'siswa' => $data['students'] ?? [],
        ])->header('Cache-Control', 'no-store');
    }

    private function resolveOwnedJadwal(int $jadwalId): array
    {
        $pegawai = $this->getPegawai();
        // Relasi `kelas` sudah dihapus (kelas_label menggantikan) — jangan eager-load.
        $jadwal = Jadwal::with(['unitSekolah'])
            ->whereKey($jadwalId)
            ->where('pegawai_id', $pegawai->id)
            ->firstOrFail();

        abort_unless($jadwal->unitSekolah, 422, 'Unit jadwal tidak tersedia.');

        return [$jadwal, $jadwal->unitSekolah];
    }

    private function studentClassRules(): array
    {
        return [
            'jadwal_id' => 'required|integer|min:1',
            'tingkat' => 'nullable|string|max:20',
            'kelas' => 'nullable|string|max:100',
            'jurusan' => 'nullable|string|max:100',
            'class_id' => 'nullable|string|max:50',
        ];
    }

    private function resolveClassPayload(Jadwal $jadwal, array $validated): array
    {
        if ($jadwal->kelas_label) {
            $parts = explode(' - ', $jadwal->kelas_label, 2);

            return [
                'tingkat' => trim($parts[0] ?? ''),
                'kelas' => trim($parts[1] ?? $parts[0] ?? ''),
                'jurusan' => '',
            ];
        }

        if (empty($validated['class_id']) || empty($validated['tingkat']) || empty($validated['kelas'])) {
            throw ValidationException::withMessages(['kelas' => 'Pilih kelas terlebih dahulu.']);
        }

        return [
            'tingkat' => trim($validated['tingkat']),
            'kelas' => trim($validated['kelas']),
            'jurusan' => trim($validated['jurusan'] ?? ''),
            'class_id' => trim($validated['class_id'] ?? ''),
        ];
    }

    private function keuanganRequest(string $endpoint, array $payload, bool $post = false)
    {
        $base = rtrim((string) config('keuangan.url'), '/');
        $key = (string) config('keuangan.key');
        if ($base === '' || $key === '' || $key === 'change-me-in-production') {
            Log::error('Integrasi keuangan belum dikonfigurasi.');

            return null;
        }

        $request = Http::acceptJson()
            ->withHeaders(['x-internal-key' => $key])
            ->connectTimeout(2)
            ->timeout($post ? 15 : 8);

        try {
            return $post
                ? $request->post($base.'/api/integration/'.$endpoint, $payload)
                : $request->get($base.'/api/integration/'.$endpoint, $payload);
        } catch (ConnectionException) {
            Log::warning('Aplikasi keuangan tidak dapat dihubungi.', ['endpoint' => $endpoint]);

            return null;
        }
    }

    public function absen()
    {
        $pegawai = $this->getPegawai();
        $pegawai->load('units');
        $hariMap = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
        $hariIniIndo = $hariMap[Carbon::now()->format('l')];

        $jadwalHariIni = $this->rememberJadwal('mobile.jadwal.'.$pegawai->id.'.'.$hariIniIndo, 900, function () use ($pegawai, $hariIniIndo) {
            return Jadwal::with(['unitSekolah', 'mataPelajaran'])
                ->where('pegawai_id', $pegawai->id)
                ->where('hari', $hariIniIndo)
                ->orderBy('jam_mulai')
                ->get();
        });

        $presensiHariIni = Presensi::where('pegawai_id', $pegawai->id)
            ->where('tanggal', Carbon::today()->toDateString())
            ->get();

        return inertia('Mobile/Absen', [
            'pegawai' => $pegawai,
            'jadwals' => $jadwalHariIni,
            'presensiHariIni' => $presensiHariIni,
            'officeAttendance' => $pegawai->wajib_kantor && ($jadwalHariIni?->isEmpty() ?? false),
            'attestation_token' => $this->attestationService->issue(),
        ]);
    }

    public function storeAbsen(Request $request)
    {
        $request->validate([
            'jadwal_id' => 'nullable|integer|min:1',
            'is_lembur' => 'nullable|boolean',
            'tipe' => 'required|in:masuk,keluar',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'accuracy' => 'required|numeric|min:0',
            'speed' => 'nullable|numeric|min:0',
            'captured_at' => 'nullable|date',
            'mock_suspect' => 'nullable|boolean',
            'pos_a_lat' => 'nullable|numeric|between:-90,90',
            'pos_a_lng' => 'nullable|numeric|between:-180,180',
            'pos_a_accuracy' => 'nullable|numeric|min:0',
            'pos_a_captured_at' => 'nullable|date',
            'attestation_token' => 'nullable|string',
            'pos_awal_lat' => 'nullable|numeric|between:-90,90',
            'pos_awal_lng' => 'nullable|numeric|between:-180,180',
            'pos_awal_accuracy' => 'nullable|numeric|min:0',
            'pos_awal_captured_at' => 'nullable|date',
            'motion_samples' => 'nullable|json',
            'foto' => ['required', 'string', 'max:'.PresensiMessages::MAX_FOTO_BASE64, 'regex:/^data:image\/\w+;base64,/'],
        ]);

        $pegawai = $this->getPegawai();
        $isLembur = (bool) $request->input('is_lembur', false);
        if ($isLembur && $request->filled('jadwal_id')) {
            throw ValidationException::withMessages(['jadwal_id' => 'Jadwal tidak boleh dipilih saat mode lembur.']);
        }
        $hariMap = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
        $hariIni = $hariMap[Carbon::now()->format('l')];
        $tipePresensi = $isLembur ? 'lembur' : ($request->filled('jadwal_id') ? 'mengajar' : 'kantor');

        if ($request->jadwal_id) {
            $jadwal = Jadwal::with('unitSekolah')
                ->whereKey($request->jadwal_id)
                ->where('pegawai_id', $pegawai->id)
                ->where('hari', $hariIni)
                ->first();
            if (! $jadwal) {
                throw ValidationException::withMessages(['jadwal_id' => PresensiMessages::PEMILIH_JADWAL_DULU]);
            }
            $unit = $jadwal->unitSekolah;
        } elseif ($isLembur) {
            $primaryUnit = $pegawai->units()->orderByPivot('is_primary', 'desc')->first();
            if (! $primaryUnit) {
                $message = PresensiMessages::PEGAWAI_TIDAK_PUNYA_UNIT;

                return response()->json(['success' => false, 'message' => $message, 'errors' => ['geofence' => $message]], 422);
            }
            $unit = $primaryUnit;
            $jadwal = null;
        } elseif ($pegawai->wajib_kantor && ! Jadwal::where('pegawai_id', $pegawai->id)->where('hari', $hariIni)->exists()) {
            $unit = $pegawai->units()->orderByPivot('is_primary', 'desc')->first();
            if (! $unit) {
                $message = PresensiMessages::PEGAWAI_TIDAK_PUNYA_UNIT;

                return response()->json(['success' => false, 'message' => $message, 'errors' => ['geofence' => $message]], 422);
            }
            $jadwal = null;
        } else {
            $message = PresensiMessages::PEMILIH_JADWAL_DULU;

            return response()->json(['success' => false, 'message' => $message, 'errors' => ['jadwal_id' => $message]], 422);
        }

        $distance = $this->calculateDistance($request->latitude, $request->longitude, $unit->latitude, $unit->longitude);

        if ($distance > $unit->radius_meter) {
            $message = sprintf(PresensiMessages::GEOFENCE_OUTSIDE, $distance, $unit->radius_meter);

            return response()->json(['success' => false, 'message' => $message, 'errors' => ['geofence' => $message]], 422);
        }

        $accuracy = (float) $request->accuracy;
        $speed = $request->filled('speed') ? (float) $request->speed : null;
        $mockSuspect = (bool) $request->input('mock_suspect', false);

        if ($accuracy !== null && $accuracy <= 0) {
            Log::warning('Percobaan presensi dengan lokasi terindikasi palsu.', [
                'user_id' => $request->user()?->id,
                'pegawai_id' => $pegawai->id,
                'nama_pegawai' => $pegawai->nama_lengkap,
                'ip' => $request->ip(),
            ]);
            $message = PresensiMessages::GEOFENCE_ACCURACY_ZERO;

            return response()->json(['success' => false, 'message' => $message, 'errors' => ['geofence' => $message]], 422);
        }
        if ($accuracy !== null && $accuracy > $unit->radius_meter) {
            $message = sprintf(PresensiMessages::GEOFENCE_ACCURACY_POOR, $accuracy, $unit->radius_meter);

            return response()->json(['success' => false, 'message' => $message, 'errors' => ['geofence' => $message]], 422);
        }

        $posisiMencurigakan = false;
        if ($request->filled('pos_a_lat') && $request->filled('pos_a_lng')) {
            $jarakAB = $this->calculateDistance($request->pos_a_lat, $request->pos_a_lng, $request->latitude, $request->longitude);
            $waktuAB = null;
            if ($request->filled('pos_a_captured_at') && $request->filled('captured_at')) {
                $waktuAB = Carbon::parse($request->pos_a_captured_at)->diffInSeconds(Carbon::parse($request->captured_at));
            }
            if ($jarakAB < 3 && $waktuAB !== null && $waktuAB > 10) {
                $posisiMencurigakan = true;
            }
            if ($request->filled('pos_a_accuracy') && (string) $request->pos_a_accuracy === (string) $request->accuracy) {
                $posisiMencurigakan = true;
            }
        }

        $lokasiPerluReview = $mockSuspect || ($accuracy !== null && $accuracy < 10);
        $capturedAt = $request->filled('captured_at') ? Carbon::parse($request->captured_at) : null;

        // Attestation verification (skip if token not provided — backward compat)
        if ($request->filled('attestation_token')) {
            $attestation = $this->attestationService->verify(
                $request->input('attestation_token'),
                $request->input('captured_at')
            );
            if (! $attestation['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => PresensiMessages::ATTESTATION_EXPIRED,
                    'errors' => ['attestation' => 'Sesi habis. Silakan refresh halaman.'],
                ], 422);
            }
        }

        // Anti-spoof multi-faktor (trajectory + motion + speed + IP, satu helper)
        $spoofDetector = new SpoofDetector;
        $spoofData = $this->computeSpoofData($request, $speed, $accuracy, $spoofDetector);
        $posisiMencurigakan = $posisiMencurigakan || $spoofData['posisi_mencurigakan'];
        $lokasiPerluReview = $lokasiPerluReview || $spoofData['posisi_mencurigakan'];

        $now = Carbon::now();
        $overlayData = [
            'label' => $isLembur ? 'BUKTI LEMBUR' : 'BUKTI PRESENSI',
            'is_lembur' => $isLembur,
            'pegawai' => $pegawai->nama_lengkap,
            'unit' => $unit->nama,
            'time' => $now->format('H:i:s').' WIB',
            'date' => $now->locale('id')->isoFormat('dddd, D MMMM YYYY'),
            'coordinates' => number_format((float) $request->latitude, 6).', '.number_format((float) $request->longitude, 6),
            'accuracy' => number_format($accuracy, 0).'m',
        ];

        $tempName = Str::uuid()->toString().'.jpg';
        $tempPath = 'temp/'.$tempName;
        $base64Data = $request->foto;
        $disk = Storage::disk('local');
        $decoded = base64_decode(explode(',', $base64Data, 2)[1] ?? '');
        $disk->put($tempPath, $decoded);

        try {
            $presensi = $this->storeAbsenTransaction($request, $pegawai, $jadwal, $unit, $distance, $isLembur, $accuracy, $speed, $capturedAt, $lokasiPerluReview, $posisiMencurigakan, $tipePresensi, $hariIni, $spoofData);
        } catch (\Throwable $e) {
            // Jangan tinggalkan sampah temp foto saat transaksi/validasi gagal
            // (job ProcessPresensiFoto tidak pernah di-dispatch di jalur ini).
            if ($disk->exists($tempPath)) {
                $disk->delete($tempPath);
            }

            throw $e;
        }

        Bus::dispatchSync(new ProcessPresensiFoto(
            $presensi->id,
            $request->tipe,
            $tempPath,
            $isLembur ? 'presensi/lembur' : 'presensi',
            $overlayData,
            ['id' => $pegawai->id, 'nama' => $pegawai->nama_lengkap, 'latitude' => $request->latitude, 'longitude' => $request->longitude]
        ));

        $successMessage = $request->tipe === 'masuk'
            ? sprintf($isLembur ? PresensiMessages::LEMBUR_MASUK_SUCCESS : PresensiMessages::ABSEN_MASUK_SUCCESS, $distance)
            : sprintf($isLembur ? PresensiMessages::LEMBUR_KELUAR_SUCCESS : PresensiMessages::ABSEN_KELUAR_SUCCESS, $distance);

        return response()->json([
            'success' => true,
            'message' => $successMessage,
        ]);
    }

    /**
     * Hitung presensi_key (harus sinkron dengan ekspresi generated column di migration
     * add_presensi_key_unique_to_presensi). Dipakai untuk lookup cepat via unique index.
     */
    private function buildPresensiKey(bool $isLembur, string $tipePresensi, $jadwalId, string $tanggal): ?string
    {
        if ($isLembur) {
            return 'L'.$tanggal;
        }
        if ($tipePresensi === 'kantor') {
            return 'K'.$tanggal;
        }
        if ($jadwalId !== null) {
            return 'M'.$tanggal.'-'.$jadwalId;
        }

        return null;
    }

    /**
     * Simpan presensi dalam satu transaksi — dipakai bersama storeAbsen
     * (mengajar / lembur / kantor) dan storeAbsenTetap (kantor).
     *
     * Anti-deadlock (fix 2026-08-15): TIDAK memakai SELECT ... FOR UPDATE.
     * Pencarian existing tanpa lock + unique index (pegawai_id, presensi_key)
     * mencegah double-absen — gap lock hilang, deadlock 1213 mustahil.
     * Race insert menang via catch UniqueConstraintViolationException (duplicate key,
     * driver-agnostic: MySQL 1062 / SQLite 19).
     * Retry 3x tetap ada sebagai jaring pengaman error concurrency lain.
     *
     * @param  Jadwal|null  $jadwal  Wajib saat tipe 'mengajar'; null untuk lembur/kantor
     * @param  string  $tipePresensi  lembur | mengajar | kantor (default 'kantor')
     * @param  string  $hariIni  Nama hari (Indonesia) untuk cek pulang-awal mengajar
     */
    private function storeAbsenTransaction(Request $request, $pegawai, ?Jadwal $jadwal, $unit, float $distance, bool $isLembur, float $accuracy, $speed, $capturedAt, bool $lokasiPerluReview, bool $posisiMencurigakan, string $tipePresensi = 'kantor', string $hariIni = '', array $spoofData = []): Presensi
    {
        return DB::transaction(function () use ($request, $pegawai, $jadwal, $unit, $distance, $isLembur, $accuracy, $speed, $capturedAt, $lokasiPerluReview, $posisiMencurigakan, $tipePresensi, $hariIni, $spoofData) {
            $tanggal = Carbon::today()->toDateString();
            $presensiKey = $this->buildPresensiKey($isLembur, $tipePresensi, $request->jadwal_id, $tanggal);

            // Cari existing TANPA lock — uniknya dijamin unique index (pegawai_id, presensi_key).
            $presensi = $presensiKey !== null
                ? Presensi::where('pegawai_id', $pegawai->id)->where('presensi_key', $presensiKey)->first()
                : null;

            // Validasi konflik sebelum set field
            if ($request->tipe === 'masuk') {
                if ($presensi && $presensi->jam_masuk) {
                    throw ValidationException::withMessages(['conflict' => PresensiMessages::SUDAH_ABSEN_MASUK]);
                }
            } else {
                if (! $presensi || ! $presensi->jam_masuk) {
                    throw ValidationException::withMessages(['conflict' => PresensiMessages::BELUM_ABSEN_MASUK]);
                }
                if ($presensi->jam_keluar) {
                    throw ValidationException::withMessages(['conflict' => PresensiMessages::SUDAH_ABSEN_KELUAR]);
                }
            }

            if (! $presensi) {
                $presensi = new Presensi([
                    'pegawai_id' => $pegawai->id,
                    'jadwal_id' => $request->jadwal_id,
                    'tanggal' => $tanggal,
                ]);
            }

            $presensi->unit_sekolah_id = $unit->id;
            $presensi->is_lembur = $isLembur;
            $presensi->tipe_presensi = $tipePresensi;

            if ($request->tipe === 'masuk') {
                $presensi->jam_masuk = Carbon::now()->format('H:i:s');
                $presensi->latitude_masuk = $request->latitude;
                $presensi->longitude_masuk = $request->longitude;
                $presensi->foto_masuk_status = 'pending';
                $presensi->jarak_masuk_meter = $distance;
                $presensi->akurasi_masuk = $accuracy;
                $presensi->kecepatan_masuk = $speed;
                $presensi->captured_at = $capturedAt;
                $presensi->lokasi_perlu_review = $lokasiPerluReview;
                $presensi->pos_a_lat = $request->filled('pos_a_lat') ? $request->pos_a_lat : null;
                $presensi->pos_a_lng = $request->filled('pos_a_lng') ? $request->pos_a_lng : null;
                $presensi->pos_a_accuracy = $request->filled('pos_a_accuracy') ? $request->pos_a_accuracy : null;
                $presensi->pos_a_captured_at = $request->filled('pos_a_captured_at') ? $request->pos_a_captured_at : null;
                $presensi->posisi_mencurigakan = $posisiMencurigakan;

                if ($isLembur) {
                    $presensi->status = 'hadir';
                    $presensi->lembur_status = 'pending';
                } else {
                    $jamMulai = $tipePresensi === 'kantor' ? $unit->jam_masuk_kantor : $jadwal->jam_mulai;
                    $presensi->status = Presensi::statusAt(Carbon::now()->format('H:i:s'), $jamMulai, (int) $unit->toleransi_menit);
                }
            } else {
                $presensi->jam_keluar = Carbon::now()->format('H:i:s');
                $presensi->latitude_keluar = $request->latitude;
                $presensi->longitude_keluar = $request->longitude;
                $presensi->foto_keluar_status = 'pending';
                $presensi->jarak_keluar_meter = $distance;
                $presensi->akurasi_keluar = $accuracy;
                $presensi->kecepatan_keluar = $speed;
                $presensi->pos_a_lat = $request->filled('pos_a_lat') ? $request->pos_a_lat : null;
                $presensi->pos_a_lng = $request->filled('pos_a_lng') ? $request->pos_a_lng : null;
                $presensi->pos_a_accuracy = $request->filled('pos_a_accuracy') ? $request->pos_a_accuracy : null;
                $presensi->pos_a_captured_at = $request->filled('pos_a_captured_at') ? $request->pos_a_captured_at : null;
                $presensi->posisi_mencurigakan = $posisiMencurigakan;

                if (! $isLembur && $tipePresensi === 'mengajar') {
                    $latestSelesai = Jadwal::where('pegawai_id', $pegawai->id)
                        ->where('hari', $hariIni)
                        ->where('jenis_jadwal', '!=', 'lembur')
                        ->max('jam_selesai');
                    if ($latestSelesai) {
                        $batasPulang = Carbon::parse($latestSelesai)->subMinutes(30)->format('H:i:s');
                        if ($presensi->jam_keluar < $batasPulang) {
                            $lokasiPerluReview = true;
                        }
                    }
                } elseif (! $isLembur && $tipePresensi === 'kantor' && $unit->jam_pulang_kantor) {
                    $batasPulang = Carbon::parse($unit->jam_pulang_kantor)->subMinutes(30)->format('H:i:s');
                    if ($presensi->jam_keluar < $batasPulang) {
                        $lokasiPerluReview = true;
                    }
                }

                $presensi->lokasi_perlu_review = $presensi->lokasi_perlu_review || $lokasiPerluReview;
            }

            // Simpan data anti-spoof v2
            $presensi->trajectory_samples = $spoofData['trajectory_samples'] ?? null;
            $presensi->motion_samples = $spoofData['motion_samples'] ?? null;
            $presensi->motion_suspect = $spoofData['motion_suspect'] ?? false;
            $presensi->ip_geo = $spoofData['ip_geo'] ?? null;

            try {
                $presensi->save();
            } catch (UniqueConstraintViolationException $e) {
                // Race: request lain membuat record yang sama antara SELECT dan INSERT.
                // Unique index (pegawai_id, presensi_key) menolak — bukan error, cukup
                // tolak absen masuk ganda. Exception ini driver-agnostic (MySQL 1062 /
                // SQLite 19) sehingga perilaku konsisten di produksi maupun test.
                if ($request->tipe === 'masuk') {
                    throw ValidationException::withMessages(['conflict' => PresensiMessages::SUDAH_ABSEN_MASUK]);
                }

                // tipe keluar: jalur ini praktis unreachable (save keluar = UPDATE pada
                // baris yang sudah ada, key tidak berubah → tidak bisa kena unique violation).
                // Defensif: re-fetch record yang baru dibuat pihak lain, lalu set jam_keluar.
                $presensi = $presensiKey !== null
                    ? Presensi::where('pegawai_id', $pegawai->id)->where('presensi_key', $presensiKey)->firstOrFail()
                    : $presensi;
                if ($presensi->jam_keluar) {
                    throw ValidationException::withMessages(['conflict' => PresensiMessages::SUDAH_ABSEN_KELUAR]);
                }
                $presensi->jam_keluar = Carbon::now()->format('H:i:s');
                $presensi->latitude_keluar = $request->latitude;
                $presensi->longitude_keluar = $request->longitude;
                $presensi->foto_keluar_status = 'pending';
                $presensi->jarak_keluar_meter = $distance;
                $presensi->akurasi_keluar = $accuracy;
                $presensi->kecepatan_keluar = $speed;
                $presensi->save();
            }

            return $presensi;
        }, 3);
    }

    private function computeSpoofData(Request $request, $speed, float $accuracy, $spoofDetector): array
    {
        // Build trajectory 3 titik
        $trajectory = [];
        if ($request->filled('pos_awal_lat') && $request->filled('pos_awal_lng')) {
            $trajectory[] = ['label' => 'awal', 'lat' => (float) $request->pos_awal_lat, 'lng' => (float) $request->pos_awal_lng, 'accuracy' => $request->filled('pos_awal_accuracy') ? (float) $request->pos_awal_accuracy : null, 'captured_at' => $request->pos_awal_captured_at];
        }
        if ($request->filled('pos_a_lat') && $request->filled('pos_a_lng')) {
            $trajectory[] = ['label' => 'a', 'lat' => (float) $request->pos_a_lat, 'lng' => (float) $request->pos_a_lng, 'accuracy' => $request->filled('pos_a_accuracy') ? (float) $request->pos_a_accuracy : null, 'captured_at' => $request->pos_a_captured_at];
        }
        $trajectory[] = ['label' => 'b', 'lat' => (float) $request->latitude, 'lng' => (float) $request->longitude, 'accuracy' => $accuracy, 'captured_at' => $request->captured_at];

        $motionSamples = $request->input('motion_samples');
        $trajResult = $spoofDetector->analyzeTrajectory($trajectory);
        $motionResult = $spoofDetector->analyzeMotion($motionSamples);
        $speedResult = $spoofDetector->analyzeSpeed($speed);
        $ipResult = $spoofDetector->analyzeGeoIp($request->ip(), (float) $request->latitude, (float) $request->longitude);

        return [
            'trajectory_samples' => $trajectory,
            'motion_samples' => $motionSamples,
            'motion_suspect' => $motionResult['suspect'],
            'ip_geo' => $ipResult['geo'] ?? null,
            'posisi_mencurigakan' => $trajResult['suspect'] || $motionResult['suspect'] || $speedResult['suspect'] || $ipResult['suspect'],
        ];
    }

    public function storeAbsenTetap(Request $request)
    {
        $pegawai = $this->getPegawai();
        abort_unless($pegawai->status_kepegawaian === 'tetap', 403, 'Hanya pegawai tetap.');

        $request->validate([
            'tipe' => 'required|in:masuk,keluar',
            'foto' => ['required', 'string', 'max:'.PresensiMessages::MAX_FOTO_BASE64, 'regex:/^data:image\/\w+;base64,/'],
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'accuracy' => 'required|numeric|min:0',
            'speed' => 'nullable|numeric|min:0',
            'captured_at' => 'nullable|date',
            'mock_suspect' => 'nullable|boolean',
            'pos_a_lat' => 'nullable|numeric|between:-90,90',
            'pos_a_lng' => 'nullable|numeric|between:-180,180',
            'pos_a_accuracy' => 'nullable|numeric|min:0',
            'pos_a_captured_at' => 'nullable|date',
            'attestation_token' => 'nullable|string',
            'pos_awal_lat' => 'nullable|numeric|between:-90,90',
            'pos_awal_lng' => 'nullable|numeric|between:-180,180',
            'pos_awal_accuracy' => 'nullable|numeric|min:0',
            'pos_awal_captured_at' => 'nullable|date',
            'motion_samples' => 'nullable|json',
        ]);

        $unit = $pegawai->units()->orderByPivot('is_primary', 'desc')->first();
        abort_unless($unit, 422, PresensiMessages::PEGAWAI_TIDAK_PUNYA_UNIT);

        $distance = $this->calculateDistance($request->latitude, $request->longitude, $unit->latitude, $unit->longitude);
        if ($distance > $unit->radius_meter) {
            return response()->json(['success' => false, 'message' => sprintf(PresensiMessages::GEOFENCE_OUTSIDE, $distance, $unit->radius_meter), 'errors' => ['geofence' => sprintf(PresensiMessages::GEOFENCE_OUTSIDE, $distance, $unit->radius_meter)]], 422);
        }

        $accuracy = (float) $request->accuracy;
        $speed = $request->filled('speed') ? (float) $request->speed : null;
        $mockSuspect = (bool) $request->input('mock_suspect', false);

        if ($accuracy !== null && $accuracy <= 0) {
            Log::warning('Percobaan presensi tetap dengan lokasi terindikasi palsu.', [
                'user_id' => $request->user()?->id, 'pegawai_id' => $pegawai->id, 'nama_pegawai' => $pegawai->nama_lengkap, 'ip' => $request->ip()]);

            return response()->json(['success' => false, 'message' => PresensiMessages::GEOFENCE_ACCURACY_ZERO, 'errors' => ['geofence' => PresensiMessages::GEOFENCE_ACCURACY_ZERO]], 422);
        }

        if ($accuracy !== null && $accuracy > $unit->radius_meter) {
            return response()->json(['success' => false, 'message' => sprintf(PresensiMessages::GEOFENCE_ACCURACY_POOR, $accuracy, $unit->radius_meter), 'errors' => ['geofence' => sprintf(PresensiMessages::GEOFENCE_ACCURACY_POOR, $accuracy, $unit->radius_meter)]], 422);
        }

        $posisiMencurigakan = false;
        if ($request->filled('pos_a_lat') && $request->filled('pos_a_lng')) {
            $jarakAB = $this->calculateDistance($request->pos_a_lat, $request->pos_a_lng, $request->latitude, $request->longitude);
            $waktuAB = null;
            if ($request->filled('pos_a_captured_at') && $request->filled('captured_at')) {
                $waktuAB = Carbon::parse($request->pos_a_captured_at)->diffInSeconds(Carbon::parse($request->captured_at));
            }
            if ($jarakAB < 3 && $waktuAB !== null && $waktuAB > 10) {
                $posisiMencurigakan = true;
            }
            if ($request->filled('pos_a_accuracy') && (string) $request->pos_a_accuracy === (string) $request->accuracy) {
                $posisiMencurigakan = true;
            }
        }

        $lokasiPerluReview = $mockSuspect || ($accuracy !== null && $accuracy < 10);
        $capturedAt = $request->filled('captured_at') ? Carbon::parse($request->captured_at) : null;
        // Attestation verification
        if ($request->filled('attestation_token')) {
            $attestation = $this->attestationService->verify($request->input('attestation_token'), $request->input('captured_at'));
            if (! $attestation['valid']) {
                return response()->json(['success' => false, 'message' => PresensiMessages::ATTESTATION_EXPIRED, 'errors' => ['attestation' => 'Sesi habis.']], 422);
            }
        }

        // Anti-spoof multi-faktor
        $spoofDetector = new SpoofDetector;
        $spoofData = $this->computeSpoofData($request, $speed, $accuracy, $spoofDetector);
        $posisiMencurigakan = $posisiMencurigakan || $spoofData['posisi_mencurigakan'];
        $lokasiPerluReview = $lokasiPerluReview || $spoofData['posisi_mencurigakan'];

        $now = Carbon::now();

        $overlayData = [
            'label' => 'ABSEN TETAP',
            'is_lembur' => false,
            'pegawai' => $pegawai->nama_lengkap,
            'unit' => $unit->nama,
            'time' => $now->format('H:i:s').' WIB',
            'date' => $now->locale('id')->isoFormat('dddd, D MMMM YYYY'),
            'coordinates' => number_format((float) $request->latitude, 6).', '.number_format((float) $request->longitude, 6),
            'accuracy' => number_format($accuracy, 0).'m',
        ];

        $tempName = Str::uuid()->toString().'.jpg';
        $tempPath = 'temp/'.$tempName;
        $base64Data = $request->foto;
        $disk = Storage::disk('local');
        $decoded = base64_decode(explode(',', $base64Data, 2)[1] ?? '');
        $disk->put($tempPath, $decoded);

        try {
            $presensi = $this->storeAbsenTransaction(
                $request,
                $pegawai,
                null, // $jadwal — presensi kantor tanpa jadwal
                $unit,
                $distance,
                false, // $isLembur
                $accuracy,
                $speed,
                $capturedAt,
                $lokasiPerluReview,
                $posisiMencurigakan,
                'kantor',
                '',
                $spoofData,
            );
        } catch (\Throwable $e) {
            // Jangan tinggalkan sampah temp foto saat transaksi/validasi gagal.
            if ($disk->exists($tempPath)) {
                $disk->delete($tempPath);
            }

            throw $e;
        }

        Bus::dispatchSync(new ProcessPresensiFoto(
            $presensi->id,
            $request->tipe,
            $tempPath,
            'presensi',
            $overlayData,
            ['id' => $pegawai->id, 'nama' => $pegawai->nama_lengkap, 'latitude' => $request->latitude, 'longitude' => $request->longitude],
        ));

        $successMessage = $request->tipe === 'masuk'
            ? sprintf(PresensiMessages::ABSEN_MASUK_SUCCESS, $distance)
            : sprintf(PresensiMessages::ABSEN_KELUAR_SUCCESS, $distance);

        return response()->json([
            'success' => true,
            'message' => $successMessage,
        ]);
    }

    public function tapJadwal(Request $request)
    {
        $pegawai = $this->getPegawai();
        abort_unless($pegawai->status_kepegawaian === 'tetap', 403, 'Hanya pegawai tetap.');

        $request->validate([
            'jadwal_id' => 'required|integer|min:1',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'accuracy' => 'required|numeric|min:0',
            'mock_suspect' => 'nullable|boolean',
        ]);

        $hariMap = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
        $hariIni = $hariMap[Carbon::now()->format('l')];

        $jadwal = Jadwal::with('unitSekolah')
            ->whereKey($request->jadwal_id)
            ->where('pegawai_id', $pegawai->id)
            ->where('hari', $hariIni)
            ->first();

        abort_unless($jadwal, 422, 'Jadwal tidak ditemukan.');
        abort_unless($jadwal->unitSekolah, 422, 'Unit jadwal tidak tersedia.');

        // Tap hanya boleh dalam rentang [jam_mulai, jam_selesai + grace] —
        // cegah presensi retroaktif untuk jadwal yang sudah lama berakhir.
        $sekarang = Carbon::now()->format('H:i:s');
        if ($jadwal->jam_mulai && $sekarang < $jadwal->jam_mulai) {
            return response()->json(['success' => false, 'message' => PresensiMessages::TAP_BELUM_DIMULAI], 422);
        }
        if ($jadwal->jam_selesai) {
            $graceTap = (int) ($jadwal->unitSekolah->toleransi_tap_menit ?? PresensiMessages::TAP_GRACE_MINUTES);
            $batasTap = Carbon::parse($jadwal->jam_selesai)->addMinutes($graceTap)->format('H:i:s');
            if ($sekarang > $batasTap) {
                return response()->json([
                    'success' => false,
                    'message' => sprintf(PresensiMessages::TAP_SUDAH_BERAKHIR, $batasTap),
                ], 422);
            }
        }

        $pagiRecord = Presensi::where('pegawai_id', $pegawai->id)
            ->whereNull('jadwal_id')
            ->where('tipe_presensi', 'kantor')
            ->where('tanggal', Carbon::today()->toDateString())
            ->exists();

        abort_unless($pagiRecord, 422, 'Silakan foto pagi terlebih dahulu.');

        $distance = $this->calculateDistance($request->latitude, $request->longitude, $jadwal->unitSekolah->latitude, $jadwal->unitSekolah->longitude);

        if ($distance > $jadwal->unitSekolah->radius_meter) {
            $message = sprintf(PresensiMessages::GEOFENCE_OUTSIDE, $distance, $jadwal->unitSekolah->radius_meter);

            return response()->json(['success' => false, 'message' => $message, 'errors' => ['geofence' => $message]], 422);
        }

        $accuracy = (float) $request->accuracy;
        if ($accuracy !== null && $accuracy <= 0) {
            $message = PresensiMessages::GEOFENCE_ACCURACY_ZERO;

            return response()->json(['success' => false, 'message' => $message, 'errors' => ['geofence' => $message]], 422);
        }

        // Anti-deadlock (pola sama dengan storeAbsenTransaction): tanpa SELECT FOR UPDATE.
        // Unique index (pegawai_id, presensi_key) menolak tap ganda — race ditangkap di 1062.
        $status = DB::transaction(function () use ($pegawai, $jadwal, $distance, $request, $accuracy) {
            try {
                $presensi = new Presensi([
                    'pegawai_id' => $pegawai->id,
                    'jadwal_id' => $jadwal->id,
                    'unit_sekolah_id' => $jadwal->unit_sekolah_id,
                    'tanggal' => Carbon::today()->toDateString(),
                    'jam_masuk' => Carbon::now()->format('H:i:s'),
                    'latitude_masuk' => $request->latitude,
                    'longitude_masuk' => $request->longitude,
                    'jarak_masuk_meter' => $distance,
                    'akurasi_masuk' => $accuracy,
                    'tipe_presensi' => 'mengajar',
                ]);
                $presensi->is_lembur = false;
                $presensi->lokasi_perlu_review = (bool) $request->input('mock_suspect', false) || $accuracy < 10;
                $presensi->status = Presensi::statusAt(Carbon::now()->format('H:i:s'), $jadwal->jam_mulai, (int) $jadwal->unitSekolah->toleransi_menit);
                $presensi->save();

                return $presensi->status;
            } catch (UniqueConstraintViolationException $e) {
                // Tap ganda: unique index (pegawai_id, presensi_key) menolak — driver-agnostic
                // (MySQL 1062 / SQLite 19). Tidak ada retry — ini bukan error concurrency.

                throw ValidationException::withMessages(['jadwal_id' => 'Jadwal ini sudah di-tap.']);
            }
        }, 3);

        return response()->json([
            'success' => true,
            'message' => $status === 'telat' ? 'Kehadiran jadwal tercatat (telat).' : 'Kehadiran jadwal tercatat.',
            'status' => $status,
        ]);
    }
}
