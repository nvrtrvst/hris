<?php

namespace App\Http\Controllers;

use App\Constants\PresensiMessages;
use App\Jobs\ProcessPresensiFoto;
use App\Models\AbsenSiswaSesi;
use App\Models\Announcement;
use App\Models\HariLibur;
use App\Models\Jadwal;
use App\Models\LaporanPiket;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\PengajuanIzin;
use App\Models\PengajuanPulangCepat;
use App\Models\PiketArea;
use App\Models\PiketPetugas;
use App\Models\PiketPos;
use App\Models\PiketPosLog;
use App\Models\PiketRound;
use App\Models\PiketSiswaTerlambat;
use App\Models\Presensi;
use App\Models\TugasLuar;
use App\Models\UnitLokasi;
use App\Services\AbsenSiswaService;
use App\Services\AttestationService;
use App\Services\GeocodingService;
use App\Services\KeuanganClient;
use App\Services\PiketService;
use App\Services\PiketSiswaTerlambatService;
use App\Services\SpoofDetector;
use App\Support\HariKerja;
use App\Traits\CalculatesDistance;
use App\Traits\ResolvesPegawai;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

    /**
     * Cache jadwal sebagai array (bukan objek Eloquent) supaya tidak corrupt
     * setelah deploy ketika model class berubah.
     */
    private function rememberJadwal(string $key, int $ttl, \Closure $callback): mixed
    {
        $cached = Cache::get($key);

        if ($cached !== null) {
            return is_array($cached) ? collect($cached) : $cached;
        }

        $result = $callback();
        $data = $result instanceof Collection ? $result->toArray() : $result;
        Cache::put($key, $data, $ttl);

        return $result;
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

        $izinHariIni = $this->izinHariIniUntuk($pegawai);

        $presensiTerbaru = Presensi::with('unitSekolah')
            ->where('pegawai_id', $pegawai->id)
            ->where('tanggal', '>=', Carbon::today()->subDays(3)->toDateString())
            ->where('tanggal', '<=', Carbon::today()->toDateString())
            ->orderBy('tanggal', 'desc')
            ->get();

        $jadwalsHariIni = $this->rememberJadwal('mobile.jadwal.'.$pegawai->id.'.'.$hariIniIndo, 900, function () use ($pegawai, $hariIniIndo) {
            return Jadwal::with(['unitSekolah', 'pegawaiMapel.mataPelajaran'])
                ->where('pegawai_id', $pegawai->id)
                ->where('hari', $hariIniIndo)
                ->orderBy('jam_mulai', 'asc')
                ->get();
        });

        $tahanPiket = $this->piketGateFor($pegawai);

        return inertia('Mobile/Dashboard', [
            'pegawai' => $pegawai,
            'presensi' => $presensiHariIni,
            'izinHariIni' => $izinHariIni,
            'presensiSeminggu' => $presensiTerbaru,
            'jadwalsHariIni' => $jadwalsHariIni,
            'alasanLibur' => HariKerja::alasanTidakBisaPresensi($pegawai, Carbon::now()),
            'piketGate' => [
                'blokir' => $tahanPiket['blokir'],
                'progress' => $tahanPiket['progress'],
            ],
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

        $presensi = Presensi::with(['unitSekolah', 'jadwal.pegawaiMapel.mataPelajaran'])
            ->where('pegawai_id', $pegawai->id)
            ->whereBetween('tanggal', [
                Carbon::createFromDate($tahun, $bulan, 1)->startOfMonth()->format('Y-m-d'),
                Carbon::createFromDate($tahun, $bulan, 1)->endOfMonth()->format('Y-m-d'),
            ])
            ->orderBy('tanggal', 'desc')
            ->get();

        // Ringkasan kehadiran bulan ini (F5) — hanya presensi harian (jadwal_id=null)
        $countsCount = $presensi->filter(fn ($p) => ! $p->is_lembur && ! $p->jadwal_id)->groupBy('status')->map->count();
        $hadir = (int) ($countsCount['hadir'] ?? 0);
        $telat = (int) ($countsCount['telat'] ?? 0);
        $sakit = (int) ($countsCount['sakit'] ?? 0);
        $izin = (int) ($countsCount['izin'] ?? 0);
        $cuti = (int) ($countsCount['cuti'] ?? 0);
        $alpa = (int) ($countsCount['alpa'] ?? 0);
        $awalBulan = Carbon::createFromDate($tahun, $bulan, 1);
        $startBulan = $awalBulan->copy()->startOfMonth();
        $endOfMonth = $awalBulan->copy()->endOfMonth();
        $today = Carbon::today();

        // Kehadiran dihitung sampai hari ini. ALPA mengikuti aturan H+1:
        // hari berjalan belum dihitung alpa -> working-days ALPA hanya sampai kemarin.
        $endKehadiran = $today->gt($endOfMonth) ? $endOfMonth->copy() : $today->copy();
        $endAlpa = $today->copy()->subDay();
        if ($endAlpa->gt($endOfMonth)) {
            $endAlpa = $endOfMonth->copy();
        }
        if ($endAlpa->lt($startBulan)) {
            $endAlpa = $startBulan->copy();
        }

        $unitIds = $pegawai->units->pluck('id')->filter()->unique()->toArray();
        $holidayKehadiran = $this->holidayCount($startBulan, $endKehadiran, $unitIds);
        $holidayAlpa = $this->holidayCount($startBulan, $endAlpa, $unitIds);

        $workingDays = $this->countWeekdaysInRange($startBulan, $endKehadiran, $holidayKehadiran);
        $workingDaysAlpa = $this->countWeekdaysInRange($startBulan, $endAlpa, $holidayAlpa);
        $totalHadir = $hadir + $telat + $izin + $sakit + $cuti + $alpa;
        $alphaTerisi = max(0, $workingDaysAlpa - $totalHadir);
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
     *
     * @param  int  $exclude  Jumlah hari libur (weekday) dalam rentang yang dikurangi.
     */
    private function countWeekdaysInRange(Carbon $start, Carbon $end, int $exclude = 0): int
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

        return max(0, $count - $exclude);
    }

    /**
     * Hitung jumlah hari libur weekday dalam rentang untuk unit pegawai
     * (libur nasional = unit_sekolah_id null, atau libur unit terkait).
     */
    private function holidayCount(Carbon $start, Carbon $end, array $unitIds): int
    {
        return HariLibur::whereBetween('tanggal', [$start->toDateString(), $end->toDateString()])
            ->where(function ($q) use ($unitIds) {
                $q->whereNull('unit_sekolah_id');
                if ($unitIds) {
                    $q->orWhereIn('unit_sekolah_id', $unitIds);
                }
            })
            ->get(['tanggal'])
            ->filter(fn ($h) => ! in_array((int) Carbon::parse($h->tanggal)->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY]))
            ->count();
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
        $pegawai->load('units');

        $jadwals = $this->rememberJadwal('mobile.jadwal.'.$pegawai->id, 900, function () use ($pegawai) {
            return Jadwal::with(['unitSekolah', 'pegawaiMapel.mataPelajaran'])
                ->where('pegawai_id', $pegawai->id)
                ->orderByRaw("CASE hari WHEN 'Senin' THEN 1 WHEN 'Selasa' THEN 2 WHEN 'Rabu' THEN 3 WHEN 'Kamis' THEN 4 WHEN 'Jumat' THEN 5 WHEN 'Sabtu' THEN 6 WHEN 'Minggu' THEN 7 END")
                ->orderBy('jam_mulai', 'asc')
                ->get()
                ->groupBy('hari');
        });

        // Presensi hari ini untuk status ringkasan
        $hariMap = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
        $hariIniIndo = $hariMap[Carbon::now()->format('l')];

        $presensiHariIni = Presensi::with('jadwal.pegawaiMapel.mataPelajaran')
            ->where('pegawai_id', $pegawai->id)
            ->where('tanggal', Carbon::today()->toDateString())
            ->get();

        // Jadwal hari ini (untuk tampilan quick view)
        $jadwalHariIni = $jadwals[$hariIniIndo] ?? collect();

        // Bawahan langsung — presensi hari ini (untuk pimpinan)
        $bawahanPresensi = collect();
        $user = auth()->user();
        if ($user && $user->hasRole('pimpinan')) {
            $bawahanIds = Pegawai::where('atasan_langsung_id', $pegawai->id)
                ->where('status_aktif', 'aktif')
                ->pluck('id');
            if ($bawahanIds->isNotEmpty()) {
                $bawahanList = Pegawai::whereIn('id', $bawahanIds)
                    ->with([
                        'user' => fn ($q) => $q->select('id', 'name'),
                        'units' => fn ($q) => $q->select('unit_sekolah.id', 'unit_sekolah.nama'),
                    ])
                    ->get();
                $todayPresensi = Presensi::whereIn('pegawai_id', $bawahanIds)
                    ->where('tanggal', Carbon::today()->toDateString())
                    ->get()
                    ->groupBy('pegawai_id');
                $bawahanPresensi = $bawahanList->map(fn ($p) => [
                    'id' => $p->id,
                    'nama' => $p->nama_lengkap,
                    'unit' => $p->units->first()?->nama ?? '',
                    'presensi' => ($todayPresensi[$p->id] ?? collect())->map(fn ($pr) => [
                        'jam_masuk' => $pr->jam_masuk,
                        'jam_keluar' => $pr->jam_keluar,
                        'status' => $pr->status,
                        'is_lembur' => $pr->is_lembur,
                    ])->values(),
                ]);
            }
        }

        return inertia('Mobile/Jadwal', [
            'pegawai' => $pegawai,
            'jadwalPerHari' => $jadwals,
            'presensiHariIni' => $presensiHariIni,
            'jadwalHariIni' => $jadwalHariIni,
            'bawahanPresensi' => $bawahanPresensi,
        ]);
    }

    public function kelasUnit(Request $request)
    {
        $pegawai = $this->getPegawai();

        $validated = $request->validate([
            'jadwal_id' => 'required|integer|min:1',
        ]);
        [$jadwal, $unit] = $this->resolveOwnedJadwal((int) $validated['jadwal_id']);

        if ($jadwal->kelas_label) {
            return response()->json(['success' => true, 'kelas' => []])->header('Cache-Control', 'no-store');
        }

        $kelas = KeuanganClient::kelasByUnit($unit->nama ?: $unit->singkatan);

        if ($kelas === null) {
            return response()->json(['success' => false, 'kelas' => [], 'message' => 'Data kelas belum tersedia.'], 502)->header('Cache-Control', 'no-store');
        }

        if (PiketService::isAreaMode($unit)) {
            $sesi = PiketService::sessionFor($pegawai, $jadwal);
            PiketService::ensureAbsenAssignments($sesi);
            PiketService::reassignAbsenIfNeeded($sesi);
            $petugas = PiketService::petugasFor($sesi, $pegawai->id);
            $assigned = $petugas && PiketService::hasAssignments($sesi)
                ? PiketService::assignedClasses($sesi, $petugas)
                : null;

            if ($assigned !== null) {
                if ($assigned === []) {
                    return response()->json(['success' => true, 'kelas' => []])->header('Cache-Control', 'no-store');
                }

                $kelas = array_values(array_filter($kelas, function ($c) use ($assigned) {
                    $label = KeuanganClient::classLabel($c);

                    return AbsenSiswaService::labelDiizinkan($assigned, $label);
                }));
            } else {
                $areaId = PiketService::areaIdUntukPegawai($pegawai, $unit);
                $allowed = PiketService::kelasArea($unit, $areaId);

                if ($allowed === []) {
                    return response()->json(['success' => false, 'kelas' => [], 'message' => 'Kelas belum dipetakan ke area. Hubungi admin.'], 422)->header('Cache-Control', 'no-store');
                }

                $kelas = array_values(array_filter($kelas, function ($c) use ($allowed) {
                    $label = KeuanganClient::classLabel($c);

                    return AbsenSiswaService::labelDiizinkan($allowed, $label);
                }));
            }
        }

        return response()->json([
            'success' => true,
            'kelas' => $kelas,
        ])->header('Cache-Control', 'no-store');
    }

    // Proxy ke API internal app keuangan untuk mengambil daftar siswa per kelas.
    // Kunci API disimpan server-side (tidak dikirim ke browser).
    public function siswaKelas(Request $request)
    {
        $validated = $request->validate($this->studentClassRules());
        [$jadwal, $unit] = $this->resolveOwnedJadwal((int) $validated['jadwal_id']);
        $class = $this->resolveClassPayload($jadwal, $validated);

        $response = KeuanganClient::request('siswa-by-class', [
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

    public function absen()
    {
        $pegawai = $this->getPegawai();
        $pegawai->load(['units', 'lokasis' => function ($q) {
            $q->where('is_active', true);
        }]);
        $hariMap = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
        $hariIniIndo = $hariMap[Carbon::now()->format('l')];

        $jadwalHariIni = $this->rememberJadwal('mobile.jadwal.'.$pegawai->id.'.'.$hariIniIndo, 900, function () use ($pegawai, $hariIniIndo) {
            return Jadwal::with(['unitSekolah', 'pegawaiMapel.mataPelajaran'])
                ->where('pegawai_id', $pegawai->id)
                ->where('hari', $hariIniIndo)
                ->orderBy('jam_mulai')
                ->get();
        });

        // Hari kerja efektif (aturan tunggal di Support\HariKerja): Sabtu/Minggu hanya
        // dibuka bila unit beroperasi hari itu (jam_kerja_sabtu_*/jam_kerja_minggu_*)
        // — kecuali pegawai punya jadwal hari ini (jadwal = bukti unit beroperasi).
        // Hari libur nasional / libur unit mematikan presensi kantor (lembur & tugas
        // luar tetap bisa).
        $hariIniBuka = HariKerja::unitBuka($pegawai, $hariIniIndo) !== null;
        $alasanLibur = HariKerja::alasanTidakBisaPresensi($pegawai, Carbon::now(), null, $jadwalHariIni->isNotEmpty());

        $presensiHariIni = Presensi::where('pegawai_id', $pegawai->id)
            ->where('tanggal', Carbon::today()->toDateString())
            ->get();

        $izinHariIni = $this->izinHariIniUntuk($pegawai);
        $tahanPiket = $this->piketGateFor($pegawai);

        // Kantor: off di hari unit tidak beroperasi atau hari libur.
        // Slot piket bukan sesi presensi — pegawai yang hari ini hanya piket
        // boleh absen masuk kantor (pintu check-in duty piket), tanpa syarat
        // wajib_kantor (duty = hari kerja terjadwal).
        $adaJadwal = $jadwalHariIni->isNotEmpty();
        $adaNonPiket = $jadwalHariIni->contains(function ($j) {
            $jenis = is_array($j) ? ($j['jenis_jadwal'] ?? null) : ($j->jenis_jadwal ?? null);

            return $jenis !== 'piket';
        });
        $piketSaja = $adaJadwal && ! $adaNonPiket;
        $kantorAktif = ($pegawai->wajib_kantor && ! $adaJadwal) || $piketSaja;
        // Alasan libur non-null = hari libur nasional/unit → kantor mati.
        if (! $hariIniBuka || $alasanLibur !== null) {
            $kantorAktif = false;
        }

        return inertia('Mobile/Absen', [
            'pegawai' => $pegawai,
            'jadwals' => $jadwalHariIni,
            'presensiHariIni' => $presensiHariIni,
            'izinHariIni' => $izinHariIni,
            'piketGate' => [
                'blokir' => $tahanPiket['blokir'],
                'progress' => $tahanPiket['progress'],
            ],
            'officeAttendance' => $kantorAktif,
            'alasanLibur' => $alasanLibur,
            // Jam izin pulang cepat yang disetujui (batas bawah pulang), null bila tidak ada.
            'jamIzinPulangCepat' => PengajuanPulangCepat::disetujuiPada($pegawai->id, Carbon::today())?->jam_diminta,
            'attestation_token' => $this->attestationService->issue(),
        ]);
    }

    /**
     * Izin/cuti/sakit yang disetujui dan mencakup hari ini (sumber otoritatif),
     * atau null bila pegawai tidak sedang izin. Cek via pengajuan_izins —
     * baris Presensi turunan dari generatePresensi bisa skip weekend / period
     * terkunci / pegawai tanpa unit, jadi tidak dipakai sebagai acuan.
     */
    private function izinHariIniUntuk(Pegawai $pegawai): ?PengajuanIzin
    {
        $tanggal = Carbon::today()->toDateString();

        return PengajuanIzin::where('pegawai_id', $pegawai->id)
            ->where('status', 'disetujui')
            ->whereIn('jenis_izin', ['izin', 'cuti', 'sakit'])
            ->whereDate('tanggal_mulai', '<=', $tanggal)
            ->whereDate('tanggal_selesai', '>=', $tanggal)
            ->first();
    }

    /**
     * Lempar ValidationException bila pegawai sedang izin/cuti/sakit yang
     * disetujui hari ini. Dipanggil SEBELUM validasi lokasi/geofence agar
     * blokirnya langsung (pesan izin, bukan "di luar radius").
     */
    private function assertTidakSedangIzin(Pegawai $pegawai): void
    {
        if ($this->izinHariIniUntuk($pegawai) !== null) {
            throw ValidationException::withMessages([
                'conflict' => 'Tanggal ini sudah tercatat sebagai izin/cuti/sakit. Tidak dapat presensi.',
            ]);
        }
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
            'is_tugas_luar' => 'nullable|boolean',
            'tujuan' => 'nullable|string|max:255',
            'keterangan' => 'nullable|string|max:1000',
        ]);

        $pegawai = $this->getPegawai();
        $this->assertTidakSedangIzin($pegawai);
        $isLembur = (bool) $request->input('is_lembur', false);
        $isTugasLuar = (bool) $request->input('is_tugas_luar', false);
        if ($request->tipe === 'keluar' && ! $isLembur && ! $isTugasLuar) {
            $tahanPiket = $this->pesanPiketBelumBeres($pegawai);
            if ($tahanPiket !== null) {
                throw ValidationException::withMessages(['piket' => $tahanPiket]);
            }
        }
        if ($isLembur && $request->filled('jadwal_id')) {
            throw ValidationException::withMessages(['jadwal_id' => 'Jadwal tidak boleh dipilih saat mode lembur.']);
        }
        if ($isTugasLuar && $isLembur) {
            throw ValidationException::withMessages(['is_tugas_luar' => 'Tidak bisa menggabung mode tugas luar dan lembur.']);
        }
        if ($isTugasLuar && $request->filled('jadwal_id')) {
            throw ValidationException::withMessages(['jadwal_id' => 'Jadwal tidak boleh dipilih saat mode tugas luar.']);
        }
        if ($isTugasLuar && ! $request->filled('tujuan')) {
            throw ValidationException::withMessages(['tujuan' => 'Tujuan tugas luar wajib diisi.']);
        }
        $hariMap = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
        $hariIni = $hariMap[Carbon::now()->format('l')];
        $tipePresensi = $isLembur ? 'lembur' : ($isTugasLuar ? 'tugas_luar' : ($request->filled('jadwal_id') ? 'mengajar' : 'kantor'));

        if ($request->jadwal_id) {
            $jadwal = Jadwal::with(['unitSekolah', 'unitLokasi'])
                ->whereKey($request->jadwal_id)
                ->where('pegawai_id', $pegawai->id)
                ->where('hari', $hariIni)
                ->first();
            if (! $jadwal) {
                throw ValidationException::withMessages(['jadwal_id' => PresensiMessages::PEMILIH_JADWAL_DULU]);
            }
            if ($jadwal->jenis_jadwal === 'piket') {
                throw ValidationException::withMessages(['jadwal_id' => PresensiMessages::PIKET_TANPA_SLIDE]);
            }
            $geo = $this->resolveGeofenceLocation($pegawai);
            if (! $geo['unit']) {
                $message = PresensiMessages::PEGAWAI_TIDAK_PUNYA_UNIT;

                return response()->json(['success' => false, 'message' => $message, 'errors' => ['geofence' => $message]], 422);
            }
            $unit = $geo['unit'];
            $unitSekolah = $jadwal->unitSekolah;
            $assignedLocations = $geo['locations'];
        } elseif ($isLembur) {
            $geo = $this->resolveGeofenceLocation($pegawai);
            if (! $geo['unit']) {
                $message = PresensiMessages::PEGAWAI_TIDAK_PUNYA_UNIT;

                return response()->json(['success' => false, 'message' => $message, 'errors' => ['geofence' => $message]], 422);
            }
            $unit = $geo['unit'];
            $unitSekolah = $geo['unitSekolah'];
            $assignedLocations = $geo['locations'];
            $jadwal = null;
        } elseif ($isTugasLuar) {
            $geo = $this->resolveGeofenceLocation($pegawai);
            if (! $geo['unit']) {
                $message = PresensiMessages::PEGAWAI_TIDAK_PUNYA_UNIT;

                return response()->json(['success' => false, 'message' => $message, 'errors' => ['geofence' => $message]], 422);
            }
            $unit = $geo['unit'];
            $unitSekolah = $geo['unitSekolah'];
            $assignedLocations = $geo['locations'];
            $jadwal = null;
        } elseif ($this->bolehMasukKantor($pegawai, $hariIni)) {
            $geo = $this->resolveGeofenceLocation($pegawai);
            if (! $geo['unit']) {
                $message = PresensiMessages::PEGAWAI_TIDAK_PUNYA_UNIT;

                return response()->json(['success' => false, 'message' => $message, 'errors' => ['geofence' => $message]], 422);
            }
            $unit = $geo['unit'];
            $unitSekolah = $geo['unitSekolah'];
            $assignedLocations = $geo['locations'];
            $jadwal = null;
        } else {
            $message = PresensiMessages::PEMILIH_JADWAL_DULU;

            return response()->json(['success' => false, 'message' => $message, 'errors' => ['jadwal_id' => $message]], 422);
        }

        // Blokir presensi reguler di hari unit tidak beroperasi (Sabtu/Minggu
        // tanpa jam kerja) atau hari libur. Lembur & tugas luar dikecualikan.
        if (! $isLembur && ! $isTugasLuar) {
            $alasanTidakAktif = HariKerja::alasanTidakBisaPresensi($pegawai, Carbon::now(), $unitSekolah, $request->filled('jadwal_id'));
            if ($alasanTidakAktif) {
                return response()->json(['success' => false, 'message' => $alasanTidakAktif, 'errors' => ['jadwal_id' => $alasanTidakAktif]], 422);
            }
        }

        $geoResult = $this->checkMultiLocationGeofence(
            $request->latitude,
            $request->longitude,
            $assignedLocations ?? collect(),
            $unit
        );
        $distance = $geoResult['distance'];

        if (! $isTugasLuar && ! $geoResult['inside']) {
            $radiusUsed = $geoResult['matchedUnit'] ? $geoResult['matchedUnit']->radius_meter : $unit->radius_meter;
            $message = sprintf(PresensiMessages::GEOFENCE_OUTSIDE, $distance, $radiusUsed);

            return response()->json(['success' => false, 'message' => $message, 'errors' => ['geofence' => $message]], 422);
        }

        // Update $unit ke matched location (untuk overlay nama, accuracy check)
        if ($geoResult['matchedUnit']) {
            $unit = $geoResult['matchedUnit'];
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

        $lokasiPerluReview = $mockSuspect || ($accuracy !== null && $accuracy < 5);
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
        $unitFullName = $unit instanceof UnitLokasi ? (($unitSekolah?->nama ?? '').' '.$unit->nama) : $unit->nama;
        $overlayData = [
            'label' => $isLembur ? 'BUKTI LEMBUR' : ($isTugasLuar ? 'TUGAS LUAR' : 'BUKTI PRESENSI'),
            'is_lembur' => $isLembur,
            'pegawai' => $pegawai->nama_lengkap,
            'unit' => trim($unitFullName),
            'time' => $now->format('H:i:s').' WIB',
            'date' => $now->locale('id')->isoFormat('dddd, D MMMM YYYY'),
            'coordinates' => number_format((float) $request->latitude, 6).', '.number_format((float) $request->longitude, 6),
            'accuracy' => number_format($accuracy, 0).'m',
            'latitude' => (float) $request->latitude,
            'longitude' => (float) $request->longitude,
        ];
        $geo = app(GeocodingService::class)->reverse($request->latitude, $request->longitude);
        $overlayData['kecamatan'] = $geo['kecamatan'] ?? null;
        $overlayData['kelurahan'] = $geo['kelurahan'] ?? null;
        $overlayData['kabupaten'] = $geo['kabupaten'] ?? null;

        $tempName = Str::uuid()->toString().'.jpg';
        $tempPath = 'temp/'.$tempName;
        $base64Data = $request->foto;
        $disk = Storage::disk('local');
        $decoded = base64_decode(explode(',', $base64Data, 2)[1] ?? '');
        $disk->put($tempPath, $decoded);

        try {
            $presensi = $this->storeAbsenTransaction($request, $pegawai, $jadwal, $unit, $distance, $isLembur, $accuracy, $speed, $capturedAt, $lokasiPerluReview, $posisiMencurigakan, $tipePresensi, $hariIni, $spoofData, $isTugasLuar, $unitSekolah ?? null);
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
            $isTugasLuar ? 'presensi/tugas-luar' : ($isLembur ? 'presensi/lembur' : 'presensi'),
            $overlayData,
            ['id' => $pegawai->id, 'nama' => $pegawai->nama_lengkap, 'latitude' => $request->latitude, 'longitude' => $request->longitude]
        ));

        $successMessage = $request->tipe === 'masuk'
            ? sprintf($isLembur ? PresensiMessages::LEMBUR_MASUK_SUCCESS : PresensiMessages::ABSEN_MASUK_SUCCESS, $distance)
            : sprintf($isLembur ? PresensiMessages::LEMBUR_KELUAR_SUCCESS : PresensiMessages::ABSEN_KELUAR_SUCCESS, $distance);

        return response()->json([
            'success' => true,
            'message' => $successMessage,
            'status' => $presensi->status,
            'jam_masuk' => $request->tipe === 'masuk' ? $presensi->jam_masuk : null,
            'batas' => $this->batasHadirFor($request, $jadwal, $unitSekolah ?? $unit, $isLembur, $isTugasLuar),
        ]);
    }

    /**
     * Batas hadir = jadwal.jam_mulai + toleransi_menit unit — mirror persis dari
     * statusAt() di storeAbsenTransaction (hanya untuk menampilkan alasan "telat").
     */
    private function batasHadirFor(Request $request, ?Jadwal $jadwal, $unit, bool $isLembur, bool $isTugasLuar): ?string
    {
        if ($request->tipe !== 'masuk' || ! $jadwal || $isLembur || $isTugasLuar) {
            return null;
        }

        return Carbon::parse($jadwal->jam_mulai)
            ->addMinutes((int) $unit->toleransi_menit)
            ->format('H:i:s');
    }

    /**
     * Hitung presensi_key (harus sinkron dengan ekspresi generated column di migration
     * add_presensi_key_unique_to_presensi). Dipakai untuk lookup cepat via unique index.
     */
    private function buildPresensiKey(bool $isLembur, string $tipePresensi, $jadwalId, string $tanggal, bool $isTugasLuar = false): ?string
    {
        if ($isLembur) {
            return 'L'.$tanggal;
        }
        if ($isTugasLuar) {
            return 'TL'.$tanggal;
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
     * Batas pulang efektif: izin pulang cepat boleh mengizinkan lebih awal, tapi
     * tidak pernah memperketat aturan normal — ambil yang paling awal.
     */
    private function batasPulangEfektif(?PengajuanPulangCepat $izin, string $batasNormal): string
    {
        if (! $izin?->jam_diminta) {
            return $batasNormal;
        }

        $jamIzin = Carbon::parse($izin->jam_diminta)->format('H:i:s');

        return $jamIzin < $batasNormal ? $jamIzin : $batasNormal;
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
    private function storeAbsenTransaction(Request $request, $pegawai, ?Jadwal $jadwal, $unit, float $distance, bool $isLembur, float $accuracy, $speed, $capturedAt, bool $lokasiPerluReview, bool $posisiMencurigakan, string $tipePresensi = 'kantor', string $hariIni = '', array $spoofData = [], bool $isTugasLuar = false, $unitSekolah = null): Presensi
    {
        return DB::transaction(function () use ($request, $pegawai, $jadwal, $unit, $distance, $isLembur, $accuracy, $speed, $capturedAt, $lokasiPerluReview, $posisiMencurigakan, $tipePresensi, $hariIni, $spoofData, $isTugasLuar, $unitSekolah) {
            $tanggal = Carbon::today()->toDateString();
            $presensiKey = $this->buildPresensiKey($isLembur, $tipePresensi, $request->jadwal_id, $tanggal, $isTugasLuar);

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

            $presensi->unit_sekolah_id = $jadwal->unit_sekolah_id ?? ($unitSekolah?->id ?? $unit->id);
            $presensi->is_lembur = $isLembur;
            $presensi->is_tugas_luar = $isTugasLuar;
            $presensi->tipe_presensi = $tipePresensi;

            $unitLokasiId = $unit instanceof UnitLokasi ? $unit->id : null;
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
                $presensi->unit_lokasi_masuk_id = $unitLokasiId;

                if ($isLembur) {
                    $presensi->status = 'hadir';
                    $presensi->lembur_status = 'pending';
                } elseif ($isTugasLuar) {
                    $presensi->status = 'hadir';
                    $tugasLuar = TugasLuar::where('pegawai_id', $pegawai->id)
                        ->where('tanggal', $tanggal)
                        ->first();
                    $presensi->tugas_luar_status = $tugasLuar ? 'disetujui' : 'pending';
                    $presensi->tugas_luar_id = $tugasLuar?->id;
                    $presensi->tujuan = $request->input('tujuan');
                    if ($request->filled('keterangan')) {
                        $presensi->keterangan = $request->input('keterangan');
                    }
                } else {
                    $timeUnit = $unitSekolah ?? $unit;
                    if ($tipePresensi === 'kantor') {
                        // Jam kerja hari itu (Sabtu/Minggu bisa beda), fallback jam kantor.
                        $jamHariIni = Carbon::now()->isSaturday()
                            ? $timeUnit->jam_kerja_sabtu_mulai
                            : (Carbon::now()->isSunday() ? $timeUnit->jam_kerja_minggu_mulai : null);
                        $jamMulai = $jamHariIni ?: $timeUnit->jam_masuk_kantor;
                    } else {
                        $jamMulai = $jadwal->jam_mulai;
                    }
                    $presensi->status = Presensi::statusAt(Carbon::now()->format('H:i:s'), $jamMulai, (int) $timeUnit->toleransi_menit);
                }

                // JP lanjutan (mapel+kelas+unit sama, gap ≤ GAP_SESI_MENIT): jam
                // dari jadwal. JP pertama yang hadir memakai jam aktual user
                // (untuk status hadir/telat); JP sebelumnya yang alpa/izin tidak
                // dihitung hadir → JP ini menjadi "pertama" (jam aktual user).
                if ($jadwal && ! $isLembur && ! $isTugasLuar) {
                    $isJpContinuation = Presensi::where('pegawai_id', $pegawai->id)
                        ->where('tanggal', $tanggal)
                        ->whereNotNull('jadwal_id')
                        ->whereNotNull('jam_masuk')
                        ->where('tipe_presensi', 'mengajar')
                        ->whereHas('jadwal', function ($q) use ($jadwal) {
                            $q->where('pegawai_mapel_id', $jadwal->pegawai_mapel_id)
                                ->where('kelas_label', $jadwal->kelas_label)
                                ->where('unit_sekolah_id', $jadwal->unit_sekolah_id);
                        })
                        ->exists();

                    if ($isJpContinuation) {
                        $presensi->jam_masuk = $jadwal->jam_mulai;
                        $presensi->status = 'hadir';
                    }

                    // Pre-fill jam_keluar dari jadwal — tidak perlu absen keluar
                    // per JP; waktunya final dari jadwal, bukan aktual.
                    // Wajib pulang mengajar (hanya pegawai tetap): pre-fill OFF —
                    // keluar dikunci via tombol Selesai Mengajar. Honorer tetap
                    // pre-fill (tanpa tombol keluar — jangan sampai buntu).
                    if (! ($unitSekolah?->wajib_pulang_mengajar && $pegawai->is_tetap)) {
                        $presensi->jam_keluar = $jadwal->jam_selesai;
                    }
                }

                // Auto-close kantor terbuka saat mulai dinas luar. UPDATE atomik dengan
                // guard whereNull('jam_keluar') mencegah double-close walau request
                // berkonkurensi tinggi — tanpa lockForUpdate (sesuai kontrak anti-deadlock).
                // query-builder update → observer pulang-awal tidak jalan (disengaja:
                // pulang untuk dinas luar bukan "pulang awal").
                if ($isTugasLuar) {
                    Presensi::where('pegawai_id', $pegawai->id)
                        ->where('tanggal', $tanggal)
                        ->where('tipe_presensi', 'kantor')
                        ->whereNull('jadwal_id')
                        ->whereNotNull('jam_masuk')
                        ->whereNull('jam_keluar')
                        ->update(['jam_keluar' => Carbon::now()->format('H:i:s')]);
                }
            } else {
                $presensi->jam_keluar = Carbon::now()->format('H:i:s');
                $presensi->latitude_keluar = $request->latitude;
                $presensi->longitude_keluar = $request->longitude;
                $presensi->foto_keluar_status = 'pending';
                $presensi->jarak_keluar_meter = $distance;
                $presensi->unit_lokasi_keluar_id = $unitLokasiId;
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

                        // ponytail: bar ini dorman selama unit.wajib_pulang_mengajar=0
                        // (semua unit belum menyalakan). Saat flag dinyalakan, prefill
                        // jam_keluar di atas juga harus dibalik untuk non-tetap — kalau
                        // tidak, barisnya auto-tutup dan bar ini tak pernah kena.
                        $unitJadwal = $jadwal?->unitSekolah;
                        if ($unitJadwal?->wajib_pulang_mengajar) {
                            $batasKeras = Carbon::parse($latestSelesai)
                                ->subMinutes((int) ($unitJadwal->pulang_sebelum_menit ?? 20))
                                ->format('H:i:s');
                            $batasKeras = $this->batasPulangEfektif(
                                PengajuanPulangCepat::disetujuiPada($pegawai->id, $tanggal),
                                $batasKeras
                            );
                            if ($presensi->jam_keluar < $batasKeras) {
                                throw ValidationException::withMessages([
                                    'conflict' => sprintf(PresensiMessages::PULANG_BELUM_WAKTUNYA, substr($batasKeras, 0, 5)),
                                ]);
                            }
                        }
                    }
                } elseif (! $isLembur && $tipePresensi === 'kantor') {
                    $jamPulang = Carbon::now()->isSaturday() && $unit->jam_kerja_sabtu_selesai
                        ? $unit->jam_kerja_sabtu_selesai
                        : $unit->jam_pulang_kantor;
                    // Izin Pulang Cepat yang disetujui membuka gate lebih awal, tapi
                    // batasnya jam yang diminta (bukan bebas sepanjang hari) dan tidak
                    // pernah lebih ketat dari jam pulang kantor. Detektor (observer)
                    // yang menandai is_pulang_awal + pulang_awal_diizinkan.
                    if ($jamPulang) {
                        $izinPulang = PengajuanPulangCepat::disetujuiPada($pegawai->id, $tanggal);
                        $batasPulang = $this->batasPulangEfektif($izinPulang, $jamPulang);
                        if ($presensi->jam_keluar < $batasPulang) {
                            throw ValidationException::withMessages([
                                'conflict' => sprintf(
                                    $izinPulang ? PresensiMessages::PULANG_BELUM_WAKTUNYA : PresensiMessages::PULANG_SEBELUM_JAM_KANTOR,
                                    substr($batasPulang, 0, 5)
                                ),
                            ]);
                        }
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

            // Auto-cover JP lanjutan (flow A honorer): saat absen masuk JP mengajar,
            // JP setelah anchor dalam sesi mengajar (forward-only, gap ≤ GAP_SESI_MENIT,
            // mapel+kelas+unit sama) otomatis dibuat row hadir dengan jam dari jadwal.
            // coverSesiMengajar skip anchor + yang sudah ada; insert aman dari
            // UniqueConstraintViolationException per-JP (tidak rollback anchor).
            if ($request->tipe === 'masuk' && $jadwal && ! $isLembur && ! $isTugasLuar && $tipePresensi === 'mengajar') {
                $this->coverSesiMengajar($pegawai, $jadwal, $hariIni, Carbon::now()->format('H:i:s'));
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

        $motionSamples = $request->filled('motion_samples')
            ? json_decode((string) $request->input('motion_samples'), true)
            : null;
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

    /**
     * Resolve lokasi geofence untuk kantor/lembur.
     * Prioritas: assigned UnitLokasi → fallback primary UnitSekolah.
     */
    private function resolveGeofenceLocation(Pegawai $pegawai): array
    {
        $unitSekolah = $pegawai->units()->orderByPivot('is_primary', 'desc')->first();

        if (! $unitSekolah) {
            return ['unit' => null, 'unitSekolah' => null, 'locations' => collect()];
        }

        $checkLocations = collect();

        // Include primary UnitSekolah if use_primary_location is true
        if ($pegawai->use_primary_location) {
            $checkLocations->push($unitSekolah);
        }

        // Include assigned UnitLokasi
        $assignedLocations = $pegawai->lokasis()
            ->where('is_active', true)
            ->where('unit_sekolah_id', $unitSekolah->id)
            ->get();
        $checkLocations = $checkLocations->concat($assignedLocations);

        if ($checkLocations->isNotEmpty()) {
            return [
                'unit' => $checkLocations->first(),
                'unitSekolah' => $unitSekolah,
                'locations' => $checkLocations,
            ];
        }

        return [
            'unit' => $unitSekolah,
            'unitSekolah' => $unitSekolah,
            'locations' => collect(),
        ];
    }

    /**
     * Cek GPS terhadap multiple locations. Return ['inside', 'distance', 'matchedUnit'].
     */
    private function checkMultiLocationGeofence(float $lat, float $lng, Collection $locations, $fallbackUnit): array
    {
        if ($locations->isNotEmpty()) {
            foreach ($locations as $loc) {
                $distance = $this->calculateDistance($lat, $lng, $loc->latitude, $loc->longitude);
                if ($distance <= $loc->radius_meter) {
                    return ['inside' => true, 'distance' => $distance, 'matchedUnit' => $loc];
                }
            }

            $minDistance = $locations->map(fn ($loc) => $this->calculateDistance($lat, $lng, $loc->latitude, $loc->longitude))->min();

            return ['inside' => false, 'distance' => $minDistance, 'matchedUnit' => null];
        }

        $distance = $this->calculateDistance($lat, $lng, $fallbackUnit->latitude, $fallbackUnit->longitude);

        return [
            'inside' => $distance <= $fallbackUnit->radius_meter,
            'distance' => $distance,
            'matchedUnit' => $distance <= $fallbackUnit->radius_meter ? $fallbackUnit : null,
        ];
    }

    public function storeAbsenTetap(Request $request)
    {
        $pegawai = $this->getPegawai();
        $this->assertTidakSedangIzin($pegawai);
        abort_unless($pegawai->is_tetap, 403, 'Hanya pegawai tetap.');

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
            'is_tugas_luar' => 'nullable|boolean',
            'is_lembur' => 'nullable|boolean',
            'tujuan' => 'nullable|string|max:255',
            'keterangan' => 'nullable|string|max:1000',
        ]);

        $isLembur = (bool) $request->input('is_lembur', false);
        $isTugasLuar = (bool) $request->input('is_tugas_luar', false) && ! $isLembur;
        if ($isTugasLuar && ! $request->filled('tujuan')) {
            return response()->json(['success' => false, 'message' => 'Tujuan tugas luar wajib diisi.', 'errors' => ['tujuan' => 'Tujuan tugas luar wajib diisi.']], 422);
        }
        if ($request->tipe === 'keluar' && ! $isTugasLuar && ! $isLembur) {
            $tahanPiket = $this->pesanPiketBelumBeres($pegawai);
            if ($tahanPiket !== null) {
                throw ValidationException::withMessages(['piket' => $tahanPiket]);
            }
        }

        // Presensi kantor tetap diblokir di hari unit tidak beroperasi / hari
        // libur. Mode tugas luar & lembur dikecualikan.
        if (! $isTugasLuar && ! $isLembur) {
            $adaJadwalHariIni = $pegawai->jadwals()->where('hari', HariKerja::hariIndo())->exists();
            $alasanTidakAktif = HariKerja::alasanTidakBisaPresensi($pegawai, Carbon::now(), null, $adaJadwalHariIni);
            if ($alasanTidakAktif) {
                return response()->json(['success' => false, 'message' => $alasanTidakAktif, 'errors' => ['libur' => $alasanTidakAktif]], 422);
            }
        }

        $geo = $this->resolveGeofenceLocation($pegawai);
        $unit = $geo['unit'];
        $unitSekolah = $geo['unitSekolah'];
        $assignedLocations = $geo['locations'];
        abort_unless($unit, 422, PresensiMessages::PEGAWAI_TIDAK_PUNYA_UNIT);

        $geoResult = $this->checkMultiLocationGeofence(
            $request->latitude,
            $request->longitude,
            $assignedLocations,
            $unit
        );
        $distance = $geoResult['distance'];
        if (! $isTugasLuar && ! $geoResult['inside']) {
            $radiusUsed = $geoResult['matchedUnit'] ? $geoResult['matchedUnit']->radius_meter : $unit->radius_meter;

            return response()->json(['success' => false, 'message' => sprintf(PresensiMessages::GEOFENCE_OUTSIDE, $distance, $radiusUsed), 'errors' => ['geofence' => sprintf(PresensiMessages::GEOFENCE_OUTSIDE, $distance, $radiusUsed)]], 422);
        }
        if ($geoResult['matchedUnit']) {
            $unit = $geoResult['matchedUnit'];
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

        $lokasiPerluReview = $mockSuspect || ($accuracy !== null && $accuracy < 5);
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
            'label' => $isLembur ? 'BUKTI LEMBUR' : ($isTugasLuar ? 'TUGAS LUAR' : 'ABSEN TETAP'),
            'is_lembur' => $isLembur,
            'pegawai' => $pegawai->nama_lengkap,
            'unit' => $unit->nama,
            'time' => $now->format('H:i:s').' WIB',
            'date' => $now->locale('id')->isoFormat('dddd, D MMMM YYYY'),
            'coordinates' => number_format((float) $request->latitude, 6).', '.number_format((float) $request->longitude, 6),
            'accuracy' => number_format($accuracy, 0).'m',
            'latitude' => (float) $request->latitude,
            'longitude' => (float) $request->longitude,
        ];
        $geo = app(GeocodingService::class)->reverse($request->latitude, $request->longitude);
        $overlayData['kecamatan'] = $geo['kecamatan'] ?? null;
        $overlayData['kelurahan'] = $geo['kelurahan'] ?? null;
        $overlayData['kabupaten'] = $geo['kabupaten'] ?? null;

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
                $isLembur,
                $accuracy,
                $speed,
                $capturedAt,
                $lokasiPerluReview,
                $posisiMencurigakan,
                $isLembur ? 'lembur' : ($isTugasLuar ? 'tugas_luar' : 'kantor'),
                '',
                $spoofData,
                $isTugasLuar,
                $unitSekolah,
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

    public function slideJadwal(Request $request)
    {
        $pegawai = $this->getPegawai();
        abort_unless($pegawai->is_tetap, 403, 'Hanya pegawai tetap.');

        // Blokir slide bila pegawai sedang izin/cuti/sakit yang disetujui —
        // dicek SEBELUM geofence agar pesan izin, bukan "di luar radius".
        $this->assertTidakSedangIzin($pegawai);

        $request->validate([
            'jadwal_id' => 'required|integer|min:1',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'accuracy' => 'required|numeric|min:0',
            'tipe' => 'nullable|in:masuk,keluar',
            'mock_suspect' => 'nullable|boolean',
        ]);

        $tipe = $request->input('tipe', 'masuk');
        if ($tipe === 'keluar') {
            $tahanPiket = $this->pesanPiketBelumBeres($pegawai);
            if ($tahanPiket !== null) {
                throw ValidationException::withMessages(['piket' => $tahanPiket]);
            }
        }

        $hariMap = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
        $hariIni = $hariMap[Carbon::now()->format('l')];

        $jadwal = Jadwal::with(['unitSekolah', 'unitLokasi'])
            ->whereKey($request->jadwal_id)
            ->where('pegawai_id', $pegawai->id)
            ->where('hari', $hariIni)
            ->first();

        abort_unless($jadwal, 422, 'Jadwal tidak ditemukan.');
        abort_unless($jadwal->unitSekolah, 422, 'Unit jadwal tidak tersedia.');
        if ($jadwal->jenis_jadwal === 'piket') {
            return response()->json(['success' => false, 'message' => PresensiMessages::PIKET_TANPA_SLIDE, 'errors' => ['jadwal_id' => PresensiMessages::PIKET_TANPA_SLIDE]], 422);
        }

        // Slide diblokir bila unit jadwal tidak beroperasi hari ini / hari libur.
        $alasanTidakAktif = HariKerja::alasanTidakBisaPresensi($pegawai, Carbon::now(), $jadwal->unitSekolah, true);
        if ($alasanTidakAktif) {
            return response()->json(['success' => false, 'message' => $alasanTidakAktif, 'errors' => ['jadwal_id' => $alasanTidakAktif]], 422);
        }

        $lokasiUnit = $jadwal->unitLokasi ?? $jadwal->unitSekolah;

        // Slide hanya boleh dalam rentang sesi mengajar [sesi_start, sesi_end + grace]
        // — cegah presensi retroaktif untuk sesi yang sudah lama berakhir.
        $sekarang = Carbon::now()->format('H:i:s');
        $sesi = $this->sesiMengajar($pegawai, $jadwal, $hariIni);
        $sesiStart = $sesi->first()->jam_mulai;
        $sesiEnd = $sesi->last()->jam_selesai;

        if ($tipe !== 'keluar') {
            if ($sekarang < $sesiStart) {
                return response()->json(['success' => false, 'message' => PresensiMessages::SLIDE_BELUM_DIMULAI], 422);
            }
            $graceSlide = (int) ($lokasiUnit->toleransi_slide_menit ?? PresensiMessages::SLIDE_GRACE_MINUTES);
            $batasSlide = Carbon::parse($sesiEnd)->addMinutes($graceSlide)->format('H:i:s');
            if ($sekarang > $batasSlide) {
                return response()->json([
                    'success' => false,
                    'message' => sprintf(PresensiMessages::SLIDE_SUDAH_BERAKHIR, $batasSlide),
                ], 422);
            }
        } else {
            // Wajib pulang mengajar: tombol keluar hanya >= sesiEnd - pulang_sebelum_menit.
            // Izin Pulang Cepat memakai jam yang diminta sebagai batas bawah, di-min
            // dengan aturan normal supaya izin tak pernah memperketat. Terlalu awal =
            // 422; terlambat tetap boleh (fallback lupa → admin ralat).
            $unitCfg = $jadwal->unitSekolah;
            if ($unitCfg->wajib_pulang_mengajar) {
                $batasPulang = Carbon::parse($sesiEnd)->subMinutes((int) $unitCfg->pulang_sebelum_menit)->format('H:i:s');
                $batasPulang = $this->batasPulangEfektif(
                    PengajuanPulangCepat::disetujuiPada($pegawai->id, Carbon::today()),
                    $batasPulang
                );
                if ($sekarang < $batasPulang) {
                    return response()->json([
                        'success' => false,
                        'message' => sprintf(PresensiMessages::PULANG_BELUM_WAKTUNYA, substr($batasPulang, 0, 5)),
                    ], 422);
                }
            }
        }

        $pagiRecord = Presensi::where('pegawai_id', $pegawai->id)
            ->whereNull('jadwal_id')
            ->where('tipe_presensi', 'kantor')
            ->where('tanggal', Carbon::today()->toDateString())
            ->first();

        abort_unless($pagiRecord, 422, 'Silakan foto pagi terlebih dahulu.');

        // Sudah absen pulang kantor = hari itu selesai (mis. realisasi izin pulang
        // cepat). Slide JP setelahnya cuma mencatat hadir penuh lewat prefill.
        if ($pagiRecord->jam_keluar) {
            return response()->json([
                'success' => false,
                'message' => PresensiMessages::SUDAH_PULANG_HARI_INI,
                'errors' => ['conflict' => PresensiMessages::SUDAH_PULANG_HARI_INI],
            ], 422);
        }

        // Geofence multi-lokasi: cek terhadap semua assigned lokasi pegawai (primary + lokasis).
        $geoData = $this->resolveGeofenceLocation($pegawai);
        $geofence = $this->checkMultiLocationGeofence((float) $request->latitude, (float) $request->longitude, $geoData['locations'], $geoData['unitSekolah'] ?? $lokasiUnit);
        if (! $geofence['inside']) {
            $radius = $geofence['matchedUnit']?->radius_meter ?? $geoData['locations']->first()?->radius_meter ?? $lokasiUnit->radius_meter;
            $message = sprintf(PresensiMessages::GEOFENCE_OUTSIDE, $geofence['distance'], $radius);

            return response()->json(['success' => false, 'message' => $message, 'errors' => ['geofence' => $message]], 422);
        }
        $distance = $geofence['distance'];
        $unitLokasiId = $geofence['matchedUnit'] instanceof UnitLokasi ? $geofence['matchedUnit']->id : null;
        $unit = $lokasiUnit;

        $accuracy = (float) $request->accuracy;
        if ($accuracy !== null && $accuracy <= 0) {
            $message = PresensiMessages::GEOFENCE_ACCURACY_ZERO;

            return response()->json(['success' => false, 'message' => $message, 'errors' => ['geofence' => $message]], 422);
        }

        // Anti-deadlock (pola sama dengan storeAbsenTransaction): tanpa SELECT FOR UPDATE
        // untuk insert; untuk checkout pakai lockForUpdate pada record yang ada.
        // Unique index (pegawai_id, presensi_key) menolak slide ganda — race ditangkap di bawah.
        $result = DB::transaction(function () use ($pegawai, $jadwal, $distance, $unitLokasiId, $request, $accuracy, $tipe, $hariIni, $sekarang) {
            if ($tipe === 'keluar') {
                // Slide pulang mencatat jam_keluar di row JP TERAKHIR sesi
                // (hari+kelas+mapel+unit sama) — row JP pertama hanya menyimpan
                // jam masuk. Jadwal tunggal: JP terakhir = dirinya sendiri.
                $target = $jadwal->jenis_jadwal === 'mengajar'
                    ? $this->presensiKeluarTarget($pegawai, $jadwal, $hariIni)
                    : null;

                $presensi = $target
                    ?? Presensi::where('pegawai_id', $pegawai->id)
                        ->where('jadwal_id', $jadwal->id)
                        ->where('tanggal', Carbon::today()->toDateString())
                        ->whereNotNull('jam_masuk')
                        ->whereNull('jam_keluar')
                        ->lockForUpdate()
                        ->first();

                if (! $presensi) {
                    throw ValidationException::withMessages(['jadwal_id' => 'Belum ada presensi masuk untuk jadwal ini.']);
                }

                $presensi->jam_keluar = Carbon::now()->format('H:i:s');
                $presensi->latitude_keluar = $request->latitude;
                $presensi->longitude_keluar = $request->longitude;
                $presensi->jarak_keluar_meter = $distance;
                $presensi->akurasi_keluar = $accuracy;
                $presensi->unit_lokasi_keluar_id = $unitLokasiId;
                $presensi->lokasi_perlu_review = (bool) $request->input('mock_suspect', false) || $accuracy < 5;
                $presensi->save();

                return ['status' => $presensi->status, 'covered' => []];
            }

            try {
                $presensi = new Presensi([
                    'pegawai_id' => $pegawai->id,
                    'jadwal_id' => $jadwal->id,
                    'unit_sekolah_id' => $jadwal->unit_sekolah_id,
                    'tanggal' => Carbon::today()->toDateString(),
                    'jam_masuk' => Carbon::now()->format('H:i:s'),
                    'latitude_masuk' => $request->latitude,
                    'longitude_masuk' => $request->longitude,
                    'unit_lokasi_masuk_id' => $unitLokasiId,
                    'jarak_masuk_meter' => $distance,
                    'akurasi_masuk' => $accuracy,
                    'tipe_presensi' => 'mengajar',
                ]);
                $presensi->is_lembur = false;
                $presensi->lokasi_perlu_review = (bool) $request->input('mock_suspect', false) || $accuracy < 5;
                $presensi->status = Presensi::statusAt(Carbon::now()->format('H:i:s'), $jadwal->jam_mulai, (int) $jadwal->unitSekolah->toleransi_menit);
                // Jam keluar mengajar final dari jadwal — tidak perlu slide pulang per JP.
                // Flag wajib_pulang_mengajar ON: pre-fill dimatikan; keluar dikunci via
                // tombol Selesai Mengajar (slide keluar) pada row JP terakhir sesi.
                if ($jadwal->jenis_jadwal === 'mengajar' && ! $jadwal->unitSekolah->wajib_pulang_mengajar) {
                    $presensi->jam_keluar = $jadwal->jam_selesai;
                }
                $presensi->save();
            } catch (UniqueConstraintViolationException $e) {
                // Slide ganda: unique index (pegawai_id, presensi_key) menolak — driver-agnostic
                // (MySQL 1062 / SQLite 19). Tidak ada retry — ini bukan error concurrency.

                throw ValidationException::withMessages(['jadwal_id' => 'Jadwal ini sudah di-slide.']);
            }

            // 1-slide-to-cover: slide JP mengajar juga mencatat JP lain dalam sesi
            // (hari+kelas+mapel+unit sama). Di luar try di atas supaya violation
            // pada JP cover di-skip per-JP, bukan membatalkan slide utama.
            $covered = $jadwal->jenis_jadwal === 'mengajar'
                ? $this->coverSesiMengajar($pegawai, $jadwal, $hariIni, $sekarang)
                : [];

            return ['status' => $presensi->status, 'covered' => $covered, 'jam_masuk' => $presensi->jam_masuk];
        }, 3);

        return response()->json([
            'success' => true,
            'message' => $tipe === 'keluar'
                ? 'Presensi pulang jadwal tercatat.'
                : ($result['status'] === 'telat' ? 'Kehadiran jadwal tercatat (telat).' : 'Kehadiran jadwal tercatat.'),
            'status' => $result['status'],
            'jam_masuk' => $tipe === 'masuk' ? $result['jam_masuk'] : null,
            'batas' => $tipe === 'masuk'
                ? Carbon::parse($jadwal->jam_mulai)->addMinutes((int) $jadwal->unitSekolah->toleransi_menit)->format('H:i:s')
                : null,
            'cover_count' => count($result['covered']),
            'covered_jadwal_ids' => $result['covered'],
        ]);
    }

    /**
     * Row presensi tujuan slide pulang: row milik JP TERAKHIR dalam sesi
     * (gap ≤ GAP_SESI_MENIT, kriteria sama: hari+kelas+mapel+unit).
     * Jadwal tunggal → row jadwal itu sendiri. Null bila row belum ada
     * (caller fallback ke lookup by jadwal_id) atau sesi sudah pulang
     * (ada jam_keluar → caller reject lewat fallback yang juga kosong).
     */
    private function presensiKeluarTarget(Pegawai $pegawai, Jadwal $jadwal, string $hariIni): ?Presensi
    {
        $sesi = $this->sesiMengajar($pegawai, $jadwal, $hariIni);
        $sesiJadwalIds = $sesi->pluck('id');

        $grupSudahPulang = Presensi::where('pegawai_id', $pegawai->id)
            ->where('tanggal', Carbon::today()->toDateString())
            ->whereIn('jadwal_id', $sesiJadwalIds)
            ->whereNotNull('jam_keluar')
            ->exists();

        if ($grupSudahPulang) {
            throw ValidationException::withMessages(['jadwal_id' => 'Sesi jadwal ini sudah di-slide pulang.']);
        }

        $lastJadwalId = $sesi->last()->id;

        return Presensi::where('pegawai_id', $pegawai->id)
            ->where('jadwal_id', $lastJadwalId)
            ->where('tanggal', Carbon::today()->toDateString())
            ->lockForUpdate()
            ->first();
    }

    /**
     * Cover otomatis JP lain dalam sesi slide (gap ≤ GAP_SESI_MENIT,
     * kriteria sama: hari+kelas+mapel+unit). JP yang sudah punya presensi
     * hari itu di-skip (pre-flight); race double-insert ditangkap unique index.
     *
     * JP cover menyimpan jam_masuk/jam_keluar dari jadwal JP masing-masing
     * (status hadir) — lokasi/foto tetap hanya di row anchor.
     *
     * @return array<int, int> ID jadwal yang tercatat oleh cover.
     */
    private function coverSesiMengajar(Pegawai $pegawai, Jadwal $jadwal, string $hariIni, string $sekarang): array
    {
        $sesi = $this->sesiMengajar($pegawai, $jadwal, $hariIni);
        $sudahAda = Presensi::where('pegawai_id', $pegawai->id)
            ->where('tanggal', Carbon::today()->toDateString())
            ->whereNotNull('jadwal_id')
            ->pluck('jadwal_id');

        $covered = [];
        foreach ($sesi as $jp) {
            if ($jp->id === $jadwal->id) {
                continue; // skip anchor (sudah di-insert caller)
            }
            if ($sudahAda->contains($jp->id)) {
                continue; // sudah ada presensi
            }
            try {
                $presensi = new Presensi([
                    'pegawai_id' => $pegawai->id,
                    'jadwal_id' => $jp->id,
                    'unit_sekolah_id' => $jp->unit_sekolah_id,
                    'tanggal' => Carbon::today()->toDateString(),
                    'tipe_presensi' => 'mengajar',
                    // Jam cover final dari jadwal JP masing-masing.
                    'jam_masuk' => $jp->jam_mulai,
                    // Flag wajib_pulang_mengajar ON: cover rows tanpa jam_keluar —
                    // terkunci via slide keluar pada row JP terakhir sesi.
                    'jam_keluar' => $jp->unitSekolah->wajib_pulang_mengajar ? null : $jp->jam_selesai,
                ]);
                $presensi->is_lembur = false;
                $presensi->status = 'hadir';
                $presensi->keterangan = sprintf('auto-cover dari slide JP #%d pukul %s', $jadwal->id, $sekarang);
                $presensi->save();
                $covered[] = $jp->id;
            } catch (UniqueConstraintViolationException) {
                // Race: JP sudah tercatat request lain — skip, jangan rollback sesi.
            }
        }

        return $covered;
    }

    /**
     * Konversi HH:MM:SS / HH:MM ke menit dari midnight.
     */
    private function toMinutes(?string $hms): int
    {
        if (! $hms) {
            return 0;
        }
        $parts = explode(':', $hms);

        return ((int) ($parts[0] ?? 0)) * 60 + ((int) ($parts[1] ?? 0));
    }

    /**
     * Ambil sesi mengajar: rantai JP konsekutif DARI $anchor ke depan,
     * gap antar JP ≤ GAP_SESI_MENIT, kriteria sama (hari+unit+kelas+mapel).
     * Forward-only: JP sebelum anchor TIDAK di-cover.
     * Kembalikan Collection terurut jam_mulai ASC.
     */
    private function sesiMengajar(Pegawai $pegawai, Jadwal $anchor, string $hari): Collection
    {
        $kandidat = Jadwal::with('unitSekolah')->where('pegawai_id', $pegawai->id)
            ->where('hari', $hari)
            ->where('jenis_jadwal', 'mengajar')
            ->where('unit_sekolah_id', $anchor->unit_sekolah_id)
            ->where('kelas_label', $anchor->kelas_label)
            ->where('pegawai_mapel_id', $anchor->pegawai_mapel_id)
            ->orderBy('jam_mulai')
            ->get();

        $anchorIdx = $kandidat->search(fn ($j) => $j->id === $anchor->id);
        if ($anchorIdx === false) {
            return collect([$anchor]);
        }

        $gapMax = PresensiMessages::GAP_SESI_MENIT;
        $chain = collect([$kandidat[$anchorIdx]]);

        // Forward: extend ke JP berikutnya selama gap ≤ gapMax
        for ($i = $anchorIdx + 1; $i < $kandidat->count(); $i++) {
            $prev = $kandidat[$i - 1];
            $cur = $kandidat[$i];
            if ($this->toMinutes($cur->jam_mulai) - $this->toMinutes($prev->jam_selesai) <= $gapMax) {
                $chain->push($cur);
            } else {
                break;
            }
        }

        return $chain;
    }

    // ── Laporan Piket Harian ──────────────────────────────────────────

    /**
     * Riwayat laporan piket milik sendiri (read-only, 30 hari terakhir).
     */
    public function piketRiwayat()
    {
        $pegawai = $this->getPegawai();

        $riwayat = LaporanPiket::where('pegawai_id', $pegawai->id)
            ->orderBy('tanggal', 'desc')
            ->limit(30)
            ->get()
            ->map(fn (LaporanPiket $lap) => [
                'id' => $lap->id,
                'tanggal' => $lap->tanggal->format('Y-m-d'),
                'status' => $lap->status,
                'done' => $lap->doneCount(),
                'total' => count(LaporanPiket::ITEM_WAJIB),
                'perlu_cek' => $lap->perluCekCount(),
                'submitted_at' => $lap->submitted_at?->format('Y-m-d H:i'),
                'items' => $lap->items ?? [],
            ]);

        return inertia('Mobile/PiketRiwayat', [
            'riwayat' => $riwayat,
            'label' => LaporanPiket::ITEM_LABEL,
        ]);
    }

    /**
     * Halaman checklist piket hari ini (mobile). prop `piket` null bila
     * pegawai tidak punya jadwal jenis=piket hari ini (empty state).
     */
    public function piket()
    {
        $pegawai = $this->getPegawai();
        $pegawai->load('units');
        $hariIniIndo = $this->hariIniIndo();
        $today = Carbon::today()->toDateString();

        $piketJadwals = Jadwal::with(['unitSekolah', 'unitLokasi'])
            ->where('pegawai_id', $pegawai->id)
            ->where('hari', $hariIniIndo)
            ->where('jenis_jadwal', 'piket')
            ->orderBy('jam_mulai')
            ->get();

        $sudahMasuk = $this->sudahMasukHariIni($pegawai->id, $today);
        $baseProps = [
            'pegawai' => $pegawai,
            'sudahMasuk' => $sudahMasuk,
            'guruUnit' => [],
            'slotKosong' => [],
            'mapelUnit' => [],
        ];

        if ($piketJadwals->isEmpty()) {
            return inertia('Mobile/Piket', $baseProps + ['piket' => null, 'butuhMasuk' => false]);
        }

        // Checklist terkunci sampai presensi masuk harian tercatat.
        if (! $sudahMasuk) {
            return inertia('Mobile/Piket', $baseProps + ['piket' => null, 'butuhMasuk' => true]);
        }

        $slot = $piketJadwals->first();
        $unitId = $slot->unit_sekolah_id;
        $unit = $slot->unitSekolah;
        $v2 = PiketService::isAreaMode($unit);

        $sesi = PiketService::sessionFor($pegawai, $slot);
        $petugas = PiketService::petugasFor($sesi, $pegawai->id);
        $laporan = $petugas?->laporan ?? PiketService::laporanFor($sesi, $pegawai->id);

        $area = null;
        $pos = [];
        $posLogs = [];
        $team = [];
        $templates = [];
        $requiredCodes = [];
        $mappedClasses = [];
        $blockReason = null;
        $assignmentVersion = (int) $sesi->assignment_version;
        $absenCoverage = ['assigned' => [], 'completed' => [], 'missing' => [], 'assigned_count' => 0, 'completed_count' => 0];

        if ($v2) {
            PiketService::ensureRounds($sesi, $slot);
            PiketService::ensureAbsenAssignments($sesi);
            PiketService::reassignAbsenIfNeeded($sesi);
            $petugas = PiketService::petugasFor($sesi, $pegawai->id);
            $laporan = $petugas?->laporan ?? PiketService::laporanFor($sesi, $pegawai->id);
            $area = $petugas?->area_id ? PiketArea::find($petugas->area_id) : null;
            $pos = PiketService::posFor($unit, $petugas);
            $posLogs = PiketService::posLogs($sesi);
            $team = PiketService::teamProgress($sesi);
            $templates = PiketService::activeTemplates($unit, $petugas);
            $requiredCodes = PiketService::requiredCodes($unit, $petugas);
            $mappedClasses = $petugas && PiketService::hasAssignments($sesi)
                ? PiketService::assignedClasses($sesi, $petugas)
                : PiketService::kelasArea($unit, $petugas?->area_id);
            $absenCoverage = $petugas ? PiketService::absenCoverage($sesi, $petugas) : $absenCoverage;
            $assignmentVersion = (int) $sesi->assignment_version;

            $blockReason = $laporan?->status === LaporanPiket::STATUS_DRAFT
                ? PiketService::sessionBlockReason($laporan)
                : null;
            if ($laporan && $petugas && $absenCoverage['assigned_count'] > 0 && $absenCoverage['missing'] === []) {
                PiketService::syncAbsenItem($laporan, $petugas);
            }
        }

        if (! $v2) {
            PiketService::syncSimpleAbsenItem($laporan, $unitId, $today);
            $laporan = $laporan?->fresh();
        }

        $guruUnit = Pegawai::where('status_aktif', 'aktif')
            ->where('id', '!=', $pegawai->id)
            ->whereHas('units', fn ($q) => $q->where('unit_sekolah.id', $unitId))
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap']);
        $guruUnitAll = $guruUnit->values();

        // Absen siswa yang sudah tersimpan hari ini (prefill/edit).
        $absensiSiswa = AbsenSiswaSesi::with(['details' => fn ($q) => $q->orderBy('nama_snapshot'), 'pegawai:id,nama_lengkap'])
            ->where('unit_sekolah_id', $unitId)
            ->where('tanggal', $today)
            ->orderBy('class_label')
            ->get()
            ->map(fn ($s) => [
                'class_id' => $s->class_id,
                'class_label' => $s->class_label,
                'done_at' => $s->done_at?->format('H:i'),
                'oleh' => $s->pegawai?->nama_lengkap,
                'details' => $s->details->map(fn ($d) => [
                    'nis' => $d->nis,
                    'nama' => $d->nama_snapshot,
                    'status' => $d->status,
                    'keterangan' => $d->keterangan,
                ])->values(),
            ])
            ->values();

        // Slot mengajar yang kosong hari ini di unit ini — sumber dropdown
        // "pilih kelas kosong" (kelas + mapel + guru dari data). Hanya slot
        // yang benar terisi (jam_masuk tercatat) yang disingkirkan; baris
        // alpa-otomatis / izin (tanpa jam_masuk) tetap dihitung kosong.
        $presensiJadwalIds = Presensi::where('tanggal', $today)
            ->whereNotNull('jadwal_id')
            ->whereNotNull('jam_masuk')
            ->pluck('jadwal_id')
            ->all();
        $slotKosong = Jadwal::with(['pegawai:id,nama_lengkap', 'pegawaiMapel.mataPelajaran:id,nama'])
            ->where('unit_sekolah_id', $unitId)
            ->where('hari', $hariIniIndo)
            ->where('jenis_jadwal', 'mengajar')
            ->whereNotIn('id', $presensiJadwalIds)
            ->orderBy('jam_mulai')
            ->get()
            ->map(fn ($j) => [
                'jadwal_id' => $j->id,
                'kelas' => $j->kelas_label,
                'mapel' => $j->pegawaiMapel?->mataPelajaran?->nama,
                'guru_pegawai_id' => $j->pegawai_id,
                'guru_nama' => $j->pegawai?->nama_lengkap,
                'jam' => substr((string) $j->jam_mulai, 0, 5).'-'.substr((string) $j->jam_selesai, 0, 5),
            ])
            ->values();

        // Mapel unit + mapel global (unit null) — unit kecil tanpa mapel
        // sendiri tetap punya pilihan.
        if ($mappedClasses !== []) {
            $absensiSiswa = $absensiSiswa
                ->filter(fn ($s) => AbsenSiswaService::labelDiizinkan($mappedClasses, $s['class_label']))
                ->values();

            $slotKosong = $slotKosong
                ->filter(fn ($s) => AbsenSiswaService::labelDiizinkan($mappedClasses, $s['kelas']))
                ->values();

            $guruAreaIds = Jadwal::where('unit_sekolah_id', $unitId)
                ->where('jenis_jadwal', 'mengajar')
                ->get(['pegawai_id', 'kelas_label'])
                ->filter(fn ($j) => AbsenSiswaService::labelDiizinkan($mappedClasses, (string) $j->kelas_label))
                ->pluck('pegawai_id')
                ->unique()
                ->all();

            $guruUnit = $guruUnit
                ->filter(fn ($g) => in_array($g->id, $guruAreaIds, true))
                ->values();
        }

        $mapelUnit = MataPelajaran::where(function ($q) use ($unitId) {
            $q->whereNull('unit_sekolah_id')->orWhere('unit_sekolah_id', $unitId);
        })
            ->orderBy('nama')
            ->get(['id', 'nama']);

        $wajibAktif = $v2 ? $requiredCodes : LaporanPiket::ITEM_WAJIB;
        $missingItems = collect($wajibAktif)
            ->filter(fn ($kode) => ! $laporan || ! $laporan->isItemDone($kode))
            ->map(fn ($kode) => ['kode' => $kode, 'label' => LaporanPiket::ITEM_LABEL[$kode] ?? $kode])
            ->values()
            ->all();
        $pendingRounds = $v2 && $sesi
            ? collect(PiketService::rounds($sesi))
                ->filter(fn ($row) => $row['selesai_at'] === null)
                ->map(fn ($row) => ['putaran' => $row['putaran'], 'area' => $row['area'], 'jendela_mulai' => $row['jendela_mulai'], 'jendela_selesai' => $row['jendela_selesai']])
                ->values()
                ->all()
            : [];
        $blockers = [
            'items' => $missingItems,
            'absen_missing' => $absenCoverage['missing'],
            'rounds' => $pendingRounds,
            'pos' => $v2 && $sesi ? PiketService::unservedRequiredPos($sesi) : [],
        ];

        $lateStudents = PiketSiswaTerlambat::where('piket_sesi_id', $sesi->id)
            ->orderBy('jam_terlambat')
            ->get()
            ->map(fn (PiketSiswaTerlambat $row) => [
                'id' => $row->id,
                'nis' => $row->nis,
                'nama' => $row->nama_snapshot,
                'class_label' => $row->class_label,
                'jam_terlambat' => substr($row->jam_terlambat, 0, 5),
                'keterangan' => $row->keterangan,
            ])
            ->values();

        return inertia('Mobile/Piket', [
            'pegawai' => $pegawai,
            'sudahMasuk' => $sudahMasuk,
            'butuhMasuk' => false,
            'piket' => [
                'tanggal' => $today,
                'slot' => [
                    'id' => $slot->id,
                    'jam_mulai' => substr((string) $slot->jam_mulai, 0, 5),
                    'jam_selesai' => substr((string) $slot->jam_selesai, 0, 5),
                    'unit' => $slot->unitSekolah?->nama,
                ],
                'windows' => $this->piketWindows($slot),
                'laporan' => $laporan,
                'wajib' => $wajibAktif,
                'label' => LaporanPiket::ITEM_LABEL,
                'v2' => $v2,
                'sesi' => $sesi ? ['id' => $sesi->id, 'status' => $sesi->status] : null,
                'area' => $area ? ['id' => $area->id, 'nama' => $area->nama] : null,
                'pos' => $pos,
                'pos_logs' => $posLogs,
                'team' => $team,
                'templates' => $templates,
                'block_reason' => $blockReason,
                'blockers' => $blockers,
                'area_kelas' => $mappedClasses,
                'late_students' => $lateStudents,
            ],
            'guruUnit' => $guruUnit,
            'guruUnitAll' => $guruUnitAll,
            'slotKosong' => $slotKosong,
            'mapelUnit' => $mapelUnit,
            'absensiSiswa' => $absensiSiswa,
        ]);
    }

    /**
     * Catat satu item checklist piket. Tiap centang wajib geofence dalam
     * radius unit + timestamp server (anti centang dari rumah / borongan
     * tanpa hadir). Response back() untuk Inertia (pakai router.post).
     */
    public function storePiketItem(Request $request)
    {
        $pegawai = $this->getPegawai();
        $this->assertTidakSedangIzin($pegawai);
        $this->assertSudahMasuk($pegawai->id);

        $request->validate([
            'kode' => 'required|in:gerbang,cek_absen,kelas_kosong,buku,insiden,absen_siswa',
            'mode' => 'nullable|in:centang,tambah,nihil',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'accuracy' => 'required|numeric|min:0',
            'terlambat_count' => 'nullable|integer|min:0|max:1000',
            'nama_guru' => 'nullable|string|max:100',
            'keterangan_absen' => 'nullable|string|max:255',
            'guru_pegawai_id' => 'nullable|integer|min:1|exists:pegawai,id',
            'kelas' => 'nullable|string|max:100',
            'slot_jadwal_id' => 'nullable|integer|min:1|exists:jadwal,id',
            'mapel_id' => 'nullable|integer|min:1|exists:mata_pelajaran,id',
            'ada_amanat' => 'nullable|boolean',
            'jenis_amanat' => 'nullable|in:tugas,pelajari_halaman,lainnya',
            'detail_amanat' => 'nullable|string|max:1000',
            'tersampaikan' => 'nullable|boolean',
            'jenis_insiden' => 'nullable|string|max:100',
            'tindakan' => 'nullable|string|max:1000',
        ]);

        $hariIniIndo = $this->hariIniIndo();
        $today = Carbon::today()->toDateString();
        $now = Carbon::now()->format('H:i');
        $slot = $this->resolvePiketSlot($pegawai, $hariIniIndo);
        $windows = $this->piketWindows($slot);
        $kode = $request->kode;
        $mode = $request->input('mode', 'centang');

        // Jendela seksi: pagi terkunci jamnya (kecuali admin sudah membuka
        // jalur telat untuk laporan ini); cek_absen-tambah boleh kapan
        // saja sampai submit (konfirmasi sore); item KBM boleh sejak seksi
        // KBM sampai laporan dikirim.
        $unit = $slot->unitSekolah;
        $sesi = PiketService::sesiFor($slot);
        $petugas = $sesi ? PiketService::petugasFor($sesi, $pegawai->id) : null;

        if (PiketService::isAreaMode($unit)) {
            $sesi = PiketService::sessionFor($pegawai, $slot);
            $petugas = PiketService::petugasFor($sesi, $pegawai->id);
            $aktif = collect(PiketService::activeTemplates($unit, $petugas))->pluck('kode')->all();
            if (! in_array($kode, $aktif, true)) {
                throw ValidationException::withMessages(['kode' => 'Item tidak tersedia untuk area/pos Anda.']);
            }
        }

        $unlocked = $sesi && (bool) LaporanPiket::where('sesi_id', $sesi->id)
            ->where('pegawai_id', $pegawai->id)
            ->where('status', LaporanPiket::STATUS_DRAFT)
            ->whereNotNull('unlocked_at')
            ->exists();
        $seksi = LaporanPiket::ITEM_SEKSI[$kode];
        $boleh = match (true) {
            $kode === LaporanPiket::ITEM_CEK_ABSEN && $mode === 'tambah' => true,
            // Absen siswa dibuka sejak pagi sampai submit (bisa diisi saat KBM).
            $kode === LaporanPiket::ITEM_ABSEN_SISWA => $now >= $windows['pagi'][0],
            $seksi === LaporanPiket::SEKSI_PAGI => $now >= $windows['pagi'][0] && ($now <= $windows['pagi'][1] || $unlocked),
            default => $now >= $windows['kbm'][0],
        };
        if (! $boleh) {
            $labelSeksi = match ($seksi) {
                LaporanPiket::SEKSI_PAGI => 'Pagi',
                LaporanPiket::SEKSI_KBM => 'Jam Belajar',
                default => 'Sore',
            };
            $jamSeksi = $windows[$seksi] ?? null;
            $pesan = match (true) {
                $jamSeksi === null => 'Bagian '.$labelSeksi.' belum dibuka.',
                $now < $jamSeksi[0] => 'Bagian '.$labelSeksi.' dibuka pukul '.substr($jamSeksi[0], 0, 5).'.',
                default => 'Bagian '.$labelSeksi.' sudah lewat pukul '.substr((string) $jamSeksi[1], 0, 5).'. Minta admin membuka jalur telat atau catat di bagian yang sesuai.',
            };

            throw ValidationException::withMessages(['kode' => $pesan]);
        }

        if ($mode === 'tambah') {
            $this->validateTambahPayload($request, $kode);
        }

        $geo = $this->checkPiketGeofence(
            $slot,
            (float) $request->latitude,
            (float) $request->longitude,
            (float) $request->accuracy
        );

        if (! $sesi) {
            $sesi = PiketService::sessionFor($pegawai, $slot);
        }

        DB::transaction(function () use ($pegawai, $slot, $sesi, $today, $kode, $mode, $request, $geo) {
            $laporan = LaporanPiket::where('sesi_id', $sesi->id)
                ->where('pegawai_id', $pegawai->id)
                ->lockForUpdate()
                ->first();

            if (! $laporan) {
                $laporan = new LaporanPiket([
                    'pegawai_id' => $pegawai->id,
                    'jadwal_id' => $slot->id,
                    'unit_sekolah_id' => $slot->unit_sekolah_id,
                    'sesi_id' => $sesi->id,
                    'tanggal' => $today,
                    'status' => LaporanPiket::STATUS_DRAFT,
                ]);
            }
            if ($laporan->status === LaporanPiket::STATUS_SUBMITTED) {
                throw ValidationException::withMessages(['laporan' => 'Laporan sudah dikirim.']);
            }

            $items = $laporan->items ?? [];
            $stamp = [
                'done_at' => Carbon::now()->format('H:i:s'),
                'lat' => (float) $request->latitude,
                'lng' => (float) $request->longitude,
                'jarak' => $geo['distance'],
            ];

            match ($kode) {
                LaporanPiket::ITEM_GERBANG => $items[$kode] = $stamp + [
                    'terlambat_count' => (int) $request->input('terlambat_count', 0),
                ],
                LaporanPiket::ITEM_BUKU => $items[$kode] = $stamp,
                LaporanPiket::ITEM_CEK_ABSEN => $items[$kode] = $this->applyCekAbsen($items[$kode] ?? null, $mode, $request, $stamp, $slot->unit_sekolah_id),
                LaporanPiket::ITEM_KELAS_KOSONG => $items[$kode] = $this->applyKelasKosong($items[$kode] ?? null, $mode, $request, $stamp, $today, $slot->unit_sekolah_id),
                LaporanPiket::ITEM_INSIDEN => $items[$kode] = $this->applyInsiden($items[$kode] ?? null, $mode, $request, $stamp),
                LaporanPiket::ITEM_ABSEN_SISWA => $items[$kode] = $this->applyAbsenSiswa($slot, $mode, $stamp, $today),
            };

            $laporan->items = $items;
            $laporan->save();
        });

        return back()->with('message', 'Tercatat.');
    }

    public function storePiketPos(Request $request)
    {
        $pegawai = $this->getPegawai();
        $this->assertTidakSedangIzin($pegawai);
        $this->assertSudahMasuk($pegawai->id);

        $validated = $request->validate([
            'pos_id' => 'required|integer|min:1|exists:piket_pos,id',
            'nihil' => 'nullable|boolean',
            'jumlah' => 'nullable|integer|min:0|max:1000',
            'catatan' => 'nullable|string|max:500',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'accuracy' => 'required|numeric|min:0',
        ]);

        $slot = $this->resolvePiketSlot($pegawai, $this->hariIniIndo());
        $unit = $slot->unitSekolah;

        if (! PiketService::isAreaMode($unit)) {
            abort(404);
        }

        $sesi = PiketService::sessionFor($pegawai, $slot);
        $petugas = PiketService::petugasFor($sesi, $pegawai->id);
        $pos = PiketPos::where('unit_sekolah_id', $unit->id)->where('aktif', true)->findOrFail($validated['pos_id']);

        if ($pos->area_id && (int) $pos->area_id !== (int) $petugas?->area_id) {
            abort(403, 'Pos ini bukan area Anda.');
        }

        $this->checkPiketGeofence(
            $slot,
            (float) $validated['latitude'],
            (float) $validated['longitude'],
            (float) $validated['accuracy']
        );

        PiketService::recordPos(
            $sesi,
            $pos,
            $pegawai->id,
            (bool) ($validated['nihil'] ?? false),
            $validated['catatan'] ?? null,
        );

        if (! empty($validated['jumlah'])) {
            PiketPosLog::where('sesi_id', $sesi->id)
                ->where('pos_id', $pos->id)
                ->update(['jumlah' => (int) $validated['jumlah']]);
        }

        return back()->with('message', 'Pos tercatat.');
    }

    /**
     * Simpan absen siswa satu kelas oleh guru piket. Sesi unik per
     * (unit, tanggal, class_id) -> kirim ulang = ganti detail (edit).
     * Item checklist ditandai lewat storePiketItem(kode=absen_siswa).
     */
    public function storePiketRound(Request $request, PiketRound $round)
    {
        $pegawai = $this->getPegawai();
        $this->assertTidakSedangIzin($pegawai);
        $this->assertSudahMasuk($pegawai->id);

        $slot = $this->resolvePiketSlot($pegawai, $this->hariIniIndo());
        $unit = $slot->unitSekolah;

        if (! PiketService::isAreaMode($unit) || (int) $round->petugas_id !== (int) $pegawai->id) {
            abort(403, 'Fase ini bukan milik Anda.');
        }

        $validated = $request->validate([
            'alasan' => 'nullable|string|min:10|max:500',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'accuracy' => 'required|numeric|min:0',
        ]);

        $this->checkPiketGeofence(
            $slot,
            (float) $validated['latitude'],
            (float) $validated['longitude'],
            (float) $validated['accuracy']
        );

        $now = Carbon::now()->format('H:i');

        if ($now < $round->jendela_mulai) {
            throw ValidationException::withMessages(['round' => 'Fase dibuka pukul '.$round->jendela_mulai.'.']);
        }

        $terlambat = $now > $round->jendela_selesai;

        if ($terlambat && blank($validated['alasan'] ?? null)) {
            throw ValidationException::withMessages(['round' => 'Isi alasan minimal 10 karakter karena fase sudah lewat jendela.']);
        }

        $round->forceFill([
            'selesai_at' => now(),
            'status' => $terlambat ? 'terlambat' : 'tepat',
            'alasan' => $validated['alasan'] ?? $round->alasan,
        ])->save();

        return back()->with('message', 'Fase selesai.');
    }

    public function storeSiswaTerlambat(Request $request)
    {
        $pegawai = $this->getPegawai();
        $this->assertTidakSedangIzin($pegawai);
        $this->assertSudahMasuk($pegawai->id);

        $validated = $request->validate([
            'tingkat' => 'required|string|max:20',
            'kelas' => 'required|string|max:100',
            'jurusan' => 'nullable|string|max:100',
            'class_id' => 'required|string|max:50',
            'class_label' => 'required|string|max:100',
            'nis' => 'required|string|max:30',
            'keterangan' => 'nullable|string|max:255',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'accuracy' => 'required|numeric|min:0',
        ]);

        $slot = $this->resolvePiketSlot($pegawai, $this->hariIniIndo());
        $unit = $slot->unitSekolah;
        $sesi = PiketService::sessionFor($pegawai, $slot);
        $petugas = PiketService::petugasFor($sesi, $pegawai->id);

        if (! $petugas) {
            abort(403, 'Anda bukan petugas piket pada sesi ini.');
        }

        $laporan = PiketService::laporanFor($sesi, $pegawai->id);

        if ($laporan?->status === LaporanPiket::STATUS_SUBMITTED) {
            throw ValidationException::withMessages(['laporan' => 'Laporan piket sudah dikirim.']);
        }

        $this->checkPiketGeofence(
            $slot,
            (float) $validated['latitude'],
            (float) $validated['longitude'],
            (float) $validated['accuracy']
        );

        $classPayload = $this->resolveClassPayload($slot, $validated);
        $response = KeuanganClient::request('siswa-by-class', [
            'unit' => $unit->nama ?: $unit->singkatan,
            ...$classPayload,
        ]);

        if (! $response?->successful()) {
            abort(502, 'Data siswa belum tersedia. Coba lagi.');
        }

        $students = collect($response->json('data.students') ?? []);
        $student = $students->first(fn ($row) => (string) ($row['nis'] ?? '') === (string) $validated['nis']);

        if (! $student) {
            throw ValidationException::withMessages(['nis' => 'Siswa tidak ditemukan di kelas tersebut.']);
        }

        $nama = trim((string) ($student['nama'] ?? $student['name'] ?? ''));
        if ($nama === '') {
            throw ValidationException::withMessages(['nis' => 'Nama siswa tidak tersedia dari sim-keu.']);
        }

        PiketSiswaTerlambatService::add($sesi, $petugas, [
            'class_id' => (string) $validated['class_id'],
            'class_label' => $validated['class_label'],
            'nis' => (string) $validated['nis'],
            'nama_snapshot' => $nama,
            'jam_terlambat' => Carbon::now()->format('H:i:s'),
            'keterangan' => $validated['keterangan'] ?? null,
        ], $request->user());

        return back()->with('message', 'Siswa terlambat dicatat.');
    }

    public function storeSiswaTerlambatBatch(Request $request)
    {
        $pegawai = $this->getPegawai();
        $this->assertTidakSedangIzin($pegawai);
        $this->assertSudahMasuk($pegawai->id);

        $validated = $request->validate([
            'tingkat' => 'required|string|max:20',
            'kelas' => 'required|string|max:100',
            'jurusan' => 'nullable|string|max:100',
            'class_id' => 'required|string|max:50',
            'class_label' => 'required|string|max:100',
            'nis' => 'required|array|min:1|max:100',
            'nis.*' => 'required|string|max:30|distinct',
            'keterangan' => 'nullable|string|max:255',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'accuracy' => 'required|numeric|min:0',
        ]);

        $slot = $this->resolvePiketSlot($pegawai, $this->hariIniIndo());
        $unit = $slot->unitSekolah;
        $sesi = PiketService::sessionFor($pegawai, $slot);
        $petugas = PiketService::petugasFor($sesi, $pegawai->id);

        if (! $petugas) {
            abort(403, 'Anda bukan petugas piket pada sesi ini.');
        }

        $laporan = PiketService::laporanFor($sesi, $pegawai->id);

        if ($laporan?->status === LaporanPiket::STATUS_SUBMITTED) {
            throw ValidationException::withMessages(['laporan' => 'Laporan piket sudah dikirim.']);
        }

        $this->checkPiketGeofence(
            $slot,
            (float) $validated['latitude'],
            (float) $validated['longitude'],
            (float) $validated['accuracy']
        );

        $classPayload = $this->resolveClassPayload($slot, $validated);
        $response = KeuanganClient::request('siswa-by-class', [
            'unit' => $unit->nama ?: $unit->singkatan,
            ...$classPayload,
        ]);

        if (! $response?->successful()) {
            abort(502, 'Data siswa belum tersedia. Coba lagi.');
        }

        $students = collect($response->json('data.students') ?? [])->keyBy(fn ($row) => (string) ($row['nis'] ?? ''));
        $rows = [];
        $missing = [];

        foreach ($validated['nis'] as $nis) {
            $student = $students->get((string) $nis);
            $nama = trim((string) ($student['nama'] ?? $student['name'] ?? ''));

            if (! $student || $nama === '') {
                $missing[] = $nis;

                continue;
            }

            $rows[] = ['nis' => (string) $nis, 'nama_snapshot' => $nama];
        }

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'nis' => 'Siswa tidak ditemukan di kelas tersebut: '.implode(', ', $missing).'.',
            ]);
        }

        $count = PiketSiswaTerlambatService::addMany($sesi, $petugas, $rows, [
            'class_id' => (string) $validated['class_id'],
            'class_label' => $validated['class_label'],
            'keterangan' => $validated['keterangan'] ?? null,
        ], $request->user());

        return back()->with('message', $count.' siswa terlambat dicatat.');
    }

    public function zeroSiswaTerlambat(Request $request)
    {
        $pegawai = $this->getPegawai();
        $this->assertTidakSedangIzin($pegawai);
        $this->assertSudahMasuk($pegawai->id);

        $slot = $this->resolvePiketSlot($pegawai, $this->hariIniIndo());
        $sesi = PiketService::sessionFor($pegawai, $slot);
        $petugas = PiketService::petugasFor($sesi, $pegawai->id);

        if (! $petugas) {
            abort(403, 'Anda bukan petugas piket pada sesi ini.');
        }

        $laporan = PiketService::laporanFor($sesi, $pegawai->id);

        if ($laporan?->status === LaporanPiket::STATUS_SUBMITTED) {
            throw ValidationException::withMessages(['laporan' => 'Laporan piket sudah dikirim.']);
        }

        PiketSiswaTerlambatService::zero($sesi, $petugas);

        return back()->with('message', 'Tidak ada siswa terlambat.');
    }

    public function destroySiswaTerlambat(Request $request, PiketSiswaTerlambat $late)
    {
        $pegawai = $this->getPegawai();
        $this->assertSudahMasuk($pegawai->id);

        if ((int) $late->pegawai_id !== (int) $pegawai->id || ! $late->tanggal->isToday()) {
            abort(403, 'Entry ini bukan milik Anda.');
        }

        $laporan = $late->sesi
            ? PiketService::laporanFor($late->sesi, $pegawai->id)
            : null;

        if ($late->sesi?->status === 'complete' || $laporan?->status === LaporanPiket::STATUS_SUBMITTED) {
            throw ValidationException::withMessages(['terlambat' => 'Laporan piket sudah dikirim.']);
        }

        PiketSiswaTerlambatService::remove($late, $request->user());

        return back()->with('message', 'Entry siswa terlambat dihapus.');
    }

    public function piketAssignmentVersion()
    {
        $pegawai = $this->getPegawai();
        $slot = $this->resolvePiketSlot($pegawai, $this->hariIniIndo());
        $sesi = PiketService::sessionFor($pegawai, $slot);
        PiketService::ensureAbsenAssignments($sesi);
        PiketService::reassignAbsenIfNeeded($sesi);
        $petugas = PiketService::petugasFor($sesi, $pegawai->id);
        $coverage = $petugas
            ? PiketService::absenCoverage($sesi, $petugas)
            : ['assigned_count' => 0, 'completed_count' => 0];

        return response()->json([
            'version' => (int) $sesi->assignment_version,
            'assigned_count' => $coverage['assigned_count'],
            'completed_count' => $coverage['completed_count'],
        ])->header('Cache-Control', 'no-store');
    }

    public function storeAbsenSiswa(Request $request)
    {
        $pegawai = $this->getPegawai();
        $this->assertTidakSedangIzin($pegawai);
        $this->assertSudahMasuk($pegawai->id);

        $validated = $request->validate([
            'class_id' => 'required|string|max:50',
            'class_label' => 'required|string|max:100',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'accuracy' => 'required|numeric|min:0',
            'siswa' => 'required|array|min:1|max:200',
            'siswa.*.nis' => 'required|string|max:30|distinct',
            'siswa.*.nama' => 'required|string|max:150',
            'siswa.*.status' => 'required|in:hadir,telat,izin,sakit,alpa',
            'siswa.*.keterangan' => 'nullable|string|max:255',
            'alasan' => 'nullable|string|max:255',
        ]);

        $today = Carbon::today()->toDateString();
        $slot = $this->resolvePiketSlot($pegawai, $this->hariIniIndo());
        $unit = $slot->unitSekolah;

        $sesiPiket = PiketService::sessionFor($pegawai, $slot);
        $petugasPiket = PiketService::petugasFor($sesiPiket, $pegawai->id);

        if (PiketService::isAreaMode($unit)) {
            PiketService::ensureAbsenAssignments($sesiPiket);
            PiketService::reassignAbsenIfNeeded($sesiPiket);
            $petugasPiket = PiketService::petugasFor($sesiPiket, $pegawai->id);

            $allowed = $petugasPiket && PiketService::hasAssignments($sesiPiket)
                ? PiketService::assignedClasses($sesiPiket, $petugasPiket)
                : PiketService::kelasArea($unit, PiketService::areaIdUntukPegawai($pegawai, $unit));

            if ($allowed === []) {
                throw ValidationException::withMessages(['class_label' => 'Belum ada kelas yang ditugaskan untuk Anda.']);
            }

            if (! AbsenSiswaService::labelDiizinkan($allowed, $validated['class_label'])) {
                abort(403, 'Kelas ini bukan bagian tugas Anda.');
            }
        }

        $this->checkPiketGeofence(
            $slot,
            (float) $validated['latitude'],
            (float) $validated['longitude'],
            (float) $validated['accuracy']
        );

        foreach ($validated['siswa'] as $i => $s) {
            if (in_array($s['status'], ['izin', 'sakit'], true) && trim((string) ($s['keterangan'] ?? '')) === '') {
                throw ValidationException::withMessages([
                    "siswa.$i.keterangan" => 'Keterangan wajib untuk siswa izin/sakit.',
                ]);
            }
        }

        DB::transaction(function () use ($pegawai, $slot, $sesiPiket, $today, $validated) {
            $laporan = LaporanPiket::where('sesi_id', $sesiPiket->id)
                ->where('pegawai_id', $pegawai->id)
                ->lockForUpdate()
                ->first();
            if ($laporan?->status === LaporanPiket::STATUS_SUBMITTED) {
                throw ValidationException::withMessages(['laporan' => 'Laporan sudah dikirim.']);
            }

            $sesi = AbsenSiswaSesi::where('unit_sekolah_id', $slot->unit_sekolah_id)
                ->where('tanggal', $today)
                ->where('class_id', $validated['class_id'])
                ->lockForUpdate()
                ->first();

            $sudahDiisi = $sesi && $sesi->done_at;

            if ($sudahDiisi && blank($validated['alasan'] ?? null)) {
                $oleh = $sesi->pegawai?->nama_lengkap ?? 'petugas';
                throw ValidationException::withMessages([
                    'alasan' => 'Kelas sudah diisi '.$oleh.' pukul '.$sesi->done_at->format('H:i').'. Isi alasan perubahan.',
                ]);
            }

            $sesi ??= new AbsenSiswaSesi([
                'unit_sekolah_id' => $slot->unit_sekolah_id,
                'tanggal' => $today,
                'class_id' => $validated['class_id'],
            ]);

            $sesi->forceFill([
                'pegawai_id' => $pegawai->id,
                'class_label' => $validated['class_label'],
            ])->save();

            AbsenSiswaService::simpan(
                $sesi,
                $validated['siswa'],
                auth()->id(),
                'petugas',
                $validated['alasan'] ?? null,
                $laporan?->id,
                $pegawai->id,
            );
        });

        $laporan = PiketService::laporanFor($sesiPiket, $pegawai->id);

        if (! $laporan) {
            $laporan = LaporanPiket::create([
                'pegawai_id' => $pegawai->id,
                'unit_sekolah_id' => $slot->unit_sekolah_id,
                'sesi_id' => $sesiPiket->id,
                'tanggal' => $today,
                'status' => LaporanPiket::STATUS_DRAFT,
                'items' => [],
            ]);
        }

        if ($petugasPiket && PiketService::isAreaMode($unit)) {
            PiketService::syncAbsenItem($laporan, $petugasPiket);
        }

        if (! PiketService::isAreaMode($unit)) {
            PiketService::syncSimpleAbsenItem($laporan, $slot->unit_sekolah_id, $today);
        }

        return back()->with('message', 'Absen siswa tersimpan.');
    }

    /**
     * Hapus satu entry piket (cek guru berhalangan / kelas kosong / insiden)
     * saat input salah. Hanya laporan DRAFT hari ini milik pegawai sendiri.
     * Bila entries jadi kosong, item ikut dihapus = kembali belum-done agar
     * bisa diproses ulang.
     */
    public function deletePiketItem(Request $request)
    {
        $pegawai = $this->getPegawai();
        $this->assertTidakSedangIzin($pegawai);

        $request->validate([
            'kode' => 'required|in:'.implode(',', [
                LaporanPiket::ITEM_CEK_ABSEN,
                LaporanPiket::ITEM_KELAS_KOSONG,
                LaporanPiket::ITEM_INSIDEN,
            ]),
            'index' => 'required|integer|min:0|max:199',
        ]);

        $today = Carbon::today()->toDateString();
        $slot = $this->resolvePiketSlot($pegawai, $this->hariIniIndo());
        $sesi = PiketService::sesiFor($slot);

        DB::transaction(function () use ($request, $pegawai, $sesi) {
            $laporan = $sesi
                ? LaporanPiket::where('sesi_id', $sesi->id)
                    ->where('pegawai_id', $pegawai->id)
                    ->lockForUpdate()
                    ->first()
                : null;
            if (! $laporan) {
                throw ValidationException::withMessages(['laporan' => 'Belum ada laporan piket hari ini.']);
            }
            if ($laporan->status !== LaporanPiket::STATUS_DRAFT) {
                throw ValidationException::withMessages(['laporan' => 'Laporan sudah dikirim, entry tidak bisa dihapus.']);
            }

            $kode = $request->kode;
            $items = $laporan->items ?? [];
            $entries = $items[$kode]['entries'] ?? [];
            if (! isset($entries[(int) $request->index])) {
                throw ValidationException::withMessages(['index' => 'Entry tidak ditemukan.']);
            }

            array_splice($entries, (int) $request->index, 1);
            if ($entries === []) {
                unset($items[$kode]);
            } else {
                $items[$kode]['entries'] = $entries;
            }

            $laporan->items = $items;
            $laporan->save();
        });

        return back()->with('message', 'Entry dihapus.');
    }

    /**
     * Kirim laporan piket. Dibuka sejak seksi sore. Semua item wajib harus
     * selesai. Setelah submitted: read-only.
     */
    public function submitPiket(Request $request)
    {
        $pegawai = $this->getPegawai();
        $this->assertTidakSedangIzin($pegawai);
        $this->assertSudahMasuk($pegawai->id);

        $slot = $this->resolvePiketSlot($pegawai, $this->hariIniIndo());
        $windows = $this->piketWindows($slot);
        $now = Carbon::now()->format('H:i');
        $mulaiSore = $windows['sore'][0];

        if (PiketService::isAreaMode($slot->unitSekolah)) {
            $akhirMengajar = PiketService::lastTeachingEnd($pegawai, $this->hariIniIndo());
            if ($akhirMengajar && $akhirMengajar > $mulaiSore) {
                $mulaiSore = $akhirMengajar;
            }
        }

        if ($now < $mulaiSore) {
            throw ValidationException::withMessages([
                'laporan' => 'Pengiriman dibuka pukul '.substr($mulaiSore, 0, 5).'. Sekarang '.$now.'.',
            ]);
        }

        $sesi = PiketService::sessionFor($pegawai, $slot);
        $petugas = PiketService::petugasFor($sesi, $pegawai->id);
        $laporan = LaporanPiket::where('sesi_id', $sesi->id)
            ->where('pegawai_id', $pegawai->id)
            ->lockForUpdate()
            ->first();

        if (! $laporan) {
            throw ValidationException::withMessages(['laporan' => 'Belum ada item piket yang dicatat. Mulai dari bagian Pagi.']);
        }
        if ($laporan->status === LaporanPiket::STATUS_SUBMITTED) {
            $pukul = $laporan->submitted_at?->format('H:i') ?: '-';
            throw ValidationException::withMessages(['laporan' => 'Laporan sudah dikirim pukul '.$pukul.'.']);
        }
        if ($blokir = PiketService::sessionBlockReason($laporan)) {
            throw ValidationException::withMessages(['laporan' => $blokir]);
        }

        $unit = $slot->unitSekolah;
        if (PiketService::isAreaMode($unit)) {
            $wajib = PiketService::requiredCodes($unit, $petugas);
            $kurang = array_values(array_filter($wajib, fn ($kode) => ! $laporan->isItemDone($kode)));

            if ($kurang !== []) {
                $labels = array_map(fn ($k) => LaporanPiket::ITEM_LABEL[$k] ?? $k, $kurang);
                throw ValidationException::withMessages(['laporan' => 'Laporan belum bisa dikirim. Lengkapi: '.implode(', ', $labels).'.']);
            }
        } elseif (! $laporan->isLengkap()) {
            $kurang = array_map(fn ($k) => LaporanPiket::ITEM_LABEL[$k], $laporan->kurangItems());
            throw ValidationException::withMessages(['laporan' => 'Laporan belum bisa dikirim. Lengkapi: '.implode(', ', $kurang).'.']);
        }

        $laporan->status = LaporanPiket::STATUS_SUBMITTED;
        $laporan->submitted_at = Carbon::now();
        $laporan->save();

        $semua = PiketPetugas::where('sesi_id', $sesi->id)
            ->where('status', '!=', 'tidak_hadir')
            ->count();
        $terkirim = LaporanPiket::where('sesi_id', $sesi->id)
            ->where('status', LaporanPiket::STATUS_SUBMITTED)
            ->count();

        if ($terkirim >= $semua && PiketService::unservedRequiredPos($sesi) === []) {
            $sesi->forceFill([
                'status' => 'complete',
                'ditutup_oleh' => auth()->id(),
                'ditutup_at' => now(),
            ])->save();
        } else {
            $sesi->forceFill(['status' => 'incomplete'])->save();
        }

        $pesan = 'Laporan piket terkirim pukul '.$laporan->submitted_at->format('H:i').'.';
        $menunggu = max(0, $semua - $terkirim);
        if ($menunggu > 0) {
            $pesan .= ' Menunggu '.$menunggu.' petugas lain.';
        } elseif ($sesi->status === 'complete') {
            $pesan .= ' Sesi piket selesai.';
        }

        return back()->with('message', $pesan);
    }

    /**
     * Syarat buka checklist piket: sudah ada presensi masuk hari ini
     * (tipe apa pun). Cegah isi checklist tanpa hadir.
     */
    private function sudahMasukHariIni(int $pegawaiId, ?string $tanggal = null): bool
    {
        return Presensi::where('pegawai_id', $pegawaiId)
            ->where('tanggal', $tanggal ?? Carbon::today()->toDateString())
            ->whereNotNull('jam_masuk')
            ->exists();
    }

    private function assertSudahMasuk(int $pegawaiId): void
    {
        if (! $this->sudahMasukHariIni($pegawaiId)) {
            throw ValidationException::withMessages([
                'presensi' => 'Lakukan presensi masuk terlebih dahulu.',
            ]);
        }
    }

    /**
     * Blokir absen keluar reguler sebelum laporan piket hari ini terkirim
     * (LaporanPiket::STATUS_SUBMITTED). Pegawai tanpa jadwal piket hari ini
     * atau laporan yang sudah terkirim lolos. Lembur/tugas luar tidak
     * melewati helper ini (dipanggil di cabang reguler saja).
     */
    private function piketGateFor(Pegawai $pegawai): array
    {
        $adaPiket = Jadwal::where('pegawai_id', $pegawai->id)
            ->where('hari', HariKerja::hariIndo())
            ->where('jenis_jadwal', 'piket')
            ->exists();
        if (! $adaPiket) {
            return ['blokir' => false, 'progress' => null, 'pesan' => null];
        }

        $laporan = LaporanPiket::where('pegawai_id', $pegawai->id)
            ->where('tanggal', Carbon::today()->toDateString())
            ->where('status', '!=', LaporanPiket::STATUS_SUBMITTED)
            ->orderBy('sesi_id')
            ->first();

        if (! $laporan && LaporanPiket::where('pegawai_id', $pegawai->id)
            ->where('tanggal', Carbon::today()->toDateString())
            ->exists()) {
            return ['blokir' => false, 'progress' => null, 'pesan' => null];
        }

        $progres = $laporan
            ? $laporan->doneCount().'/'.count(LaporanPiket::ITEM_WAJIB)
            : '0/'.count(LaporanPiket::ITEM_WAJIB);

        return [
            'blokir' => true,
            'progress' => $progres,
            'pesan' => sprintf(PresensiMessages::PIKET_KELUAR_TERTAHAN, $progres),
        ];
    }

    private function pesanPiketBelumBeres(Pegawai $pegawai): ?string
    {
        return $this->piketGateFor($pegawai)['pesan'];
    }

    /**
     * Boleh absen masuk kantor: wajib_kantor tanpa jadwal hari ini, ATAU
     * hari ini hanya ada slot piket (pintu check-in duty, tanpa syarat wajib).
     */
    private function bolehMasukKantor(Pegawai $pegawai, string $hariIni): bool
    {
        $adaJadwal = Jadwal::where('pegawai_id', $pegawai->id)->where('hari', $hariIni)->exists();
        if ($pegawai->wajib_kantor && ! $adaJadwal) {
            return true;
        }
        if (! $adaJadwal) {
            return false;
        }

        return ! Jadwal::where('pegawai_id', $pegawai->id)
            ->where('hari', $hariIni)
            ->where('jenis_jadwal', '!=', 'piket')
            ->exists();
    }

    private function hariIniIndo(): string
    {
        $hariMap = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];

        return $hariMap[Carbon::now()->format('l')];
    }

    private function resolvePiketSlot(Pegawai $pegawai, string $hariIniIndo): Jadwal
    {
        $slot = Jadwal::with(['unitSekolah', 'unitLokasi'])
            ->where('pegawai_id', $pegawai->id)
            ->where('hari', $hariIniIndo)
            ->where('jenis_jadwal', 'piket')
            ->orderBy('jam_mulai')
            ->first();

        abort_unless($slot, 422, 'Tidak ada jadwal piket hari ini.');

        return $slot;
    }

    /**
     * Bagi slot piket jadi 3 seksi proporsional (pagi/kbm/sore).
     * Fallback: jam kantor unit, lalu 07:00-15:00.
     */
    private function piketWindows(Jadwal $slot): array
    {
        $mulai = substr((string) ($slot->jam_mulai ?: ''), 0, 5);
        $selesai = substr((string) ($slot->jam_selesai ?: ''), 0, 5);
        if (! $mulai || ! $selesai || $selesai <= $mulai) {
            $unit = $slot->unitSekolah;
            $mulai = substr((string) ($unit?->jam_masuk_kantor ?: '07:00'), 0, 5);
            $selesai = substr((string) ($unit?->jam_pulang_kantor ?: '15:00'), 0, 5);
        }
        if (! $mulai || ! $selesai || $selesai <= $mulai) {
            $mulai = '07:00';
            $selesai = '15:00';
        }

        $awal = $this->toMinutes($mulai.':00');
        $total = $this->toMinutes($selesai.':00') - $awal;
        $fmt = fn (int $m): string => sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
        $p1 = $awal + (int) ($total / 3);
        $p2 = $awal + (int) ($total * 2 / 3);

        return ['pagi' => [$mulai, $fmt($p1)], 'kbm' => [$fmt($p1), $fmt($p2)], 'sore' => [$fmt($p2), $selesai]];
    }

    /**
     * Geofence piket: lokasi slot (unitLokasi bila ada) else unit sekolah.
     * Aturan sama dengan absen: tolak di luar radius + akurasi nol.
     */
    private function checkPiketGeofence(Jadwal $slot, float $lat, float $lng, float $accuracy): array
    {
        $fallback = $slot->unitSekolah ?? $slot->unitLokasi;
        abort_unless($fallback, 422, 'Unit piket tidak tersedia.');
        $locations = $slot->unitLokasi ? collect([$slot->unitLokasi]) : collect();

        $result = $this->checkMultiLocationGeofence($lat, $lng, $locations, $fallback);
        if (! $result['inside']) {
            $radius = $result['matchedUnit']?->radius_meter ?? $fallback->radius_meter;
            throw ValidationException::withMessages([
                'geofence' => sprintf(PresensiMessages::GEOFENCE_OUTSIDE, $result['distance'], $radius),
            ]);
        }
        if ($accuracy <= 0) {
            throw ValidationException::withMessages(['geofence' => PresensiMessages::GEOFENCE_ACCURACY_ZERO]);
        }
        if ($accuracy > $fallback->radius_meter) {
            throw ValidationException::withMessages([
                'geofence' => sprintf(PresensiMessages::GEOFENCE_ACCURACY_POOR, $accuracy, $fallback->radius_meter),
            ]);
        }

        return $result;
    }

    private function validateTambahPayload(Request $request, string $kode): void
    {
        if ($kode === LaporanPiket::ITEM_CEK_ABSEN) {
            if (! $request->filled('guru_pegawai_id') && ! $request->filled('nama_guru')) {
                throw ValidationException::withMessages(['guru_pegawai_id' => 'Pilih guru atau isi nama manual.']);
            }
            if (! $request->filled('keterangan_absen')) {
                throw ValidationException::withMessages(['keterangan_absen' => 'Keterangan wajib diisi.']);
            }
        }
        if ($kode === LaporanPiket::ITEM_KELAS_KOSONG) {
            if ($request->filled('slot_jadwal_id')) {
                // Jalur slot: cukup slot + amanat (kelas/mapel/guru dari DB).
            } elseif (! $request->filled('guru_pegawai_id') || ! $request->filled('kelas')) {
                throw ValidationException::withMessages(['kelas' => 'Pilih slot kosong, atau isi kelas dan guru manual.']);
            }
            if ($request->boolean('ada_amanat') && (! $request->filled('jenis_amanat') || ! $request->filled('detail_amanat'))) {
                throw ValidationException::withMessages(['detail_amanat' => 'Jenis dan isi amanat wajib diisi.']);
            }
        }
        if ($kode === LaporanPiket::ITEM_INSIDEN) {
            if (! $request->filled('jenis_insiden') || ! $request->filled('tindakan')) {
                throw ValidationException::withMessages(['tindakan' => 'Jenis insiden dan tindakan wajib diisi.']);
            }
        }
    }

    private function applyCekAbsen(?array $item, string $mode, Request $request, array $stamp, ?int $unitId): array
    {
        $item ??= ['entries' => []];
        if ($mode === 'tambah') {
            $guruId = null;
            $nama = trim((string) $request->nama_guru);
            $sumber = 'manual';

            if ($request->filled('guru_pegawai_id')) {
                $guru = Pegawai::select('id', 'nama_lengkap', 'status_aktif')->find($request->guru_pegawai_id);
                if (! $guru || $guru->status_aktif !== 'aktif'
                    || ($unitId && ! $guru->belongsToUnit($unitId))) {
                    throw ValidationException::withMessages(['guru_pegawai_id' => 'Guru tidak valid untuk unit ini.']);
                }

                $guruId = (int) $guru->id;
                $nama = $guru->nama_lengkap;
                $sumber = 'daftar';
            }

            $item['entries'][] = [
                'guru_pegawai_id' => $guruId,
                'nama_guru' => $nama,
                'sumber' => $sumber,
                'keterangan' => trim((string) $request->keterangan_absen),
                'created_at' => $stamp['done_at'],
                'lat' => $stamp['lat'],
                'lng' => $stamp['lng'],
                'jarak' => $stamp['jarak'],
            ];

            return $item;
        }

        return $item + $stamp;
    }

    private function applyKelasKosong(?array $item, string $mode, Request $request, array $stamp, string $today, ?int $unitId): array
    {
        $item ??= ['entries' => []];
        if ($mode === 'nihil') {
            if (! empty($item['entries'])) {
                throw ValidationException::withMessages(['kelas' => 'Sudah ada entry kelas kosong, tidak bisa tandai nihil.']);
            }
            $item['nihil'] = true;

            return $item + $stamp;
        }

        // Jalur slot: kelas/mapel/guru diambil dari jadwal di DB (client tak dipercaya).
        // Jalur manual: dari input + mapel divalidasi milik unit (atau global).
        if ($request->filled('slot_jadwal_id')) {
            $slot = Jadwal::with(['pegawai:id,nama_lengkap', 'pegawaiMapel.mataPelajaran:id,nama,unit_sekolah_id'])
                ->whereKey($request->slot_jadwal_id)
                ->where('jenis_jadwal', 'mengajar')
                ->where('hari', $this->hariIniIndo())
                ->first();
            if (! $slot || ($unitId && (int) $slot->unit_sekolah_id !== (int) $unitId)) {
                throw ValidationException::withMessages(['kelas' => 'Slot kosong tidak valid.']);
            }
            $kelas = (string) $slot->kelas_label;
            $mapel = $slot->pegawaiMapel?->mataPelajaran?->nama;
            $guruId = (int) $slot->pegawai_id;
            $guruNama = $slot->pegawai?->nama_lengkap ?? '';
            $sumber = 'slot';
            $slotId = $slot->id;
        } else {
            $mapel = null;
            if ($request->filled('mapel_id')) {
                $mapelRow = MataPelajaran::select('id', 'nama', 'unit_sekolah_id')->find($request->mapel_id);
                if (! $mapelRow || ($mapelRow->unit_sekolah_id && $unitId && (int) $mapelRow->unit_sekolah_id !== (int) $unitId)) {
                    throw ValidationException::withMessages(['mapel_id' => 'Mata pelajaran tidak valid untuk unit ini.']);
                }
                $mapel = $mapelRow->nama;
            }
            $guru = Pegawai::select('id', 'nama_lengkap')->find($request->guru_pegawai_id);
            $kelas = trim((string) $request->kelas);
            $guruId = (int) $request->guru_pegawai_id;
            $guruNama = $guru?->nama_lengkap ?? '';
            $sumber = 'manual';
            $slotId = null;
        }

        $cek = $this->validasiKehadiranGuru($guruId, $today, $slotId);
        $item['entries'][] = [
            'kelas' => $kelas,
            'mapel' => $mapel,
            'guru_pegawai_id' => $guruId,
            'guru_nama' => $guruNama,
            'sumber' => $sumber,
            'slot_jadwal_id' => $slotId,
            'ada_amanat' => $request->boolean('ada_amanat'),
            'jenis_amanat' => $request->input('jenis_amanat'),
            'detail_amanat' => $request->input('detail_amanat'),
            'tersampaikan' => $request->boolean('tersampaikan'),
            'created_at' => $stamp['done_at'],
            'lat' => $stamp['lat'],
            'lng' => $stamp['lng'],
            'jarak' => $stamp['jarak'],
            'validasi' => $cek['cocok'] ? 'cocok' : 'perlu_cek',
            'validasi_catatan' => $cek['catatan'],
        ];
        unset($item['nihil']);

        return $item + $stamp;
    }

    private function applyInsiden(?array $item, string $mode, Request $request, array $stamp): array
    {
        $item ??= ['entries' => []];
        if ($mode === 'nihil') {
            if (! empty($item['entries'])) {
                throw ValidationException::withMessages(['insiden' => 'Sudah ada entry insiden, tidak bisa tandai nihil.']);
            }
            $item['nihil'] = true;

            return $item + $stamp;
        }

        $item['entries'][] = [
            'jenis' => trim((string) $request->jenis_insiden),
            'tindakan' => trim((string) $request->tindakan),
            'created_at' => $stamp['done_at'],
            'lat' => $stamp['lat'],
            'lng' => $stamp['lng'],
            'jarak' => $stamp['jarak'],
        ];
        unset($item['nihil']);

        return $item + $stamp;
    }

    private function applyAbsenSiswa(Jadwal $slot, string $mode, array $stamp, string $today): array
    {
        $count = AbsenSiswaSesi::where('unit_sekolah_id', $slot->unit_sekolah_id)
            ->where('tanggal', $today)
            ->count();

        if ($mode === 'nihil') {
            if ($count > 0) {
                throw ValidationException::withMessages(['absen_siswa' => 'Sudah ada absen siswa hari ini, tidak bisa tandai nihil.']);
            }

            return ['nihil' => true, 'sesi_count' => 0] + $stamp;
        }

        if ($count === 0) {
            throw ValidationException::withMessages(['absen_siswa' => 'Simpan absen minimal satu kelas dulu.']);
        }

        return ['sesi_count' => $count] + $stamp;
    }

    /**
     * Cross-check entry kelas kosong: guru benar absen bila ada izin
     * disetujui mencakup tanggal itu, atau tidak ada presensi masuk.
     * Guru yang tercatat hadir = perlu_cek (flag untuk admin).
     */
    private function validasiKehadiranGuru(int $guruPegawaiId, string $tanggal, ?int $jadwalId = null): array
    {
        $izin = PengajuanIzin::where('pegawai_id', $guruPegawaiId)
            ->where('status', 'disetujui')
            ->whereDate('tanggal_mulai', '<=', $tanggal)
            ->whereDate('tanggal_selesai', '>=', $tanggal)
            ->first();
        if ($izin) {
            return ['cocok' => true, 'catatan' => 'Izin disetujui: '.$izin->jenis_izin];
        }

        $presensi = Presensi::where('pegawai_id', $guruPegawaiId)
            ->where('tanggal', $tanggal)
            ->whereNotNull('jam_masuk')
            ->whereIn('status', ['hadir', 'telat']);

        if ($jadwalId !== null) {
            $presensi->where('jadwal_id', $jadwalId);
        }

        if ($presensi->exists()) {
            return [
                'cocok' => false,
                'catatan' => $jadwalId !== null ? 'Guru tercatat hadir di kelas ini' : 'Guru tercatat hadir',
            ];
        }

        return ['cocok' => true, 'catatan' => 'Tidak ada presensi masuk'];
    }
}
