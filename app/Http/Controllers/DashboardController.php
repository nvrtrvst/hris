<?php

namespace App\Http\Controllers;

use App\Helpers\HariHelper;
use App\Models\Jadwal;
use App\Models\KomponenGaji;
use App\Models\Pegawai;
use App\Models\Penggajian;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function perbandinganUnit(Request $request)
    {
        $user = auth()->user();
        if (! $user->can('view_all_units')) {
            abort(403, 'Akses ditolak.');
        }

        $period = $request->input('period', 'this_month');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        if ($period === 'custom' && $startDate && $endDate) {
            // use provided dates
        } elseif ($period === 'last_month') {
            $startDate = Carbon::now('Asia/Jakarta')->subMonth()->startOfMonth()->format('Y-m-d');
            $endDate = Carbon::now('Asia/Jakarta')->subMonth()->endOfMonth()->format('Y-m-d');
        } else {
            $startDate = Carbon::now('Asia/Jakarta')->startOfMonth()->format('Y-m-d');
            $endDate = Carbon::now('Asia/Jakarta')->endOfMonth()->format('Y-m-d');
        }

        $cacheKey = "dashboard:perbandingan-unit:{$startDate}:{$endDate}";
        $data = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($startDate, $endDate) {
            $units = UnitSekolah::pluck('nama', 'id');

            $stats = Presensi::selectRaw('
                unit_sekolah_id,
                COUNT(*) as total,
                SUM(CASE WHEN status = "hadir" THEN 1 ELSE 0 END) as total_hadir,
                SUM(CASE WHEN status = "telat" THEN 1 ELSE 0 END) as total_telat,
                SUM(CASE WHEN status = "sakit" THEN 1 ELSE 0 END) as total_sakit,
                SUM(CASE WHEN status = "izin" THEN 1 ELSE 0 END) as total_izin,
                SUM(CASE WHEN status = "cuti" THEN 1 ELSE 0 END) as total_cuti,
                SUM(CASE WHEN status = "alpa" THEN 1 ELSE 0 END) as total_alpa
            ')
                ->whereBetween('tanggal', [$startDate, $endDate])
                ->where('is_lembur', '!=', true)
                ->groupBy('unit_sekolah_id')
                ->get();

            $results = [];
            foreach ($stats as $row) {
                $totalHadirTelat = $row->total_hadir + $row->total_telat;
                $results[] = [
                    'unit_id' => $row->unit_sekolah_id,
                    'unit_nama' => $units[$row->unit_sekolah_id] ?? 'Unknown',
                    'total' => (int) $row->total,
                    'total_hadir' => (int) $row->total_hadir,
                    'total_telat' => (int) $row->total_telat,
                    'total_sakit' => (int) $row->total_sakit,
                    'total_izin' => (int) $row->total_izin,
                    'total_cuti' => (int) $row->total_cuti,
                    'total_alpa' => (int) $row->total_alpa,
                    'kehadiran_persen' => $row->total > 0 ? round(($totalHadirTelat / $row->total) * 100, 1) : 0,
                ];
            }

            // Include units without data
            $withData = collect($results)->pluck('unit_id')->toArray();
            foreach ($units as $unitId => $unitNama) {
                if (! in_array($unitId, $withData)) {
                    $results[] = [
                        'unit_id' => $unitId,
                        'unit_nama' => $unitNama,
                        'total' => 0,
                        'total_hadir' => 0,
                        'total_telat' => 0,
                        'total_sakit' => 0,
                        'total_izin' => 0,
                        'total_cuti' => 0,
                        'total_alpa' => 0,
                        'kehadiran_persen' => 0,
                    ];
                }
            }

            usort($results, fn ($a, $b) => $a['kehadiran_persen'] <=> $b['kehadiran_persen']);

            return $results;
        });

        return inertia('PerbandinganUnit', [
            'units' => $data,
            'filter' => [
                'period' => $period,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ]);
    }

    public function index(Request $request)
    {
        $user = auth()->user();

        $roleType = 'Staff';
        if ($user->can('view_dashboard')) {
            $roleType = 'Super Admin'; // Any admin module view
        }

        if ($roleType === 'Staff') {
            $pegawai = Pegawai::where('user_id', $user->id)->first();

            $hadirBulanIni = 0;
            $jadwalBulanIni = 0;

            if ($pegawai) {
                $hadirBulanIni = Presensi::where('pegawai_id', $pegawai->id)
                    ->whereBetween('tanggal', [
                        Carbon::now('Asia/Jakarta')->startOfMonth()->format('Y-m-d'),
                        Carbon::now('Asia/Jakarta')->endOfMonth()->format('Y-m-d'),
                    ])
                    ->whereIn('status', ['hadir', 'telat'])
                    ->count();

                // Hitung kasar jadwal kerja
                $jadwalBulanIni = 22; // Asumsi 22 hari kerja untuk Staff (bisa disesuaikan nanti)
            }

            return inertia('DashboardSelfService', [
                'stats' => [
                    'hadir_bulan_ini' => $hadirBulanIni,
                    'jadwal_bulan_ini' => $jadwalBulanIni,
                ],
            ]);
        }

        // Logic for Admin / HR / Unit Dashboard
        $today = Carbon::today('Asia/Jakarta');

        // [PERF] Cache agregat dashboard per user (5 menit) agar tidak hitung ulang tiap load.
        $cacheKey = 'dashboard:admin:'.$user->id;
        $admin = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($user, $today) {
            // 1. Total Pegawai Aktif
            $pegawaiQuery = Pegawai::where('status_aktif', 'aktif');
            if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
                $pegawaiQuery->forUnit($user->unit_sekolah_id);
            }
            $totalPegawai = $pegawaiQuery->count();
            $totalUnit = UnitSekolah::count();

            // 2. Kehadiran Hari Ini (Real vs Jadwal)
            $hariIniIndo = HariHelper::hariIniIndo();

            $jadwalQuery = Jadwal::where('hari', $hariIniIndo);
            if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
                $jadwalQuery->where('unit_sekolah_id', $user->unit_sekolah_id);
            }
            $pegawaiDijadwalkan = $jadwalQuery->distinct('pegawai_id')->count('pegawai_id');

            $presensiQuery = Presensi::where('tanggal', $today)->whereIn('status', ['hadir', 'telat']);
            if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
                $presensiQuery->whereHas('pegawai', function ($q) use ($user) {
                    $q->forUnit($user->unit_sekolah_id);
                });
            }
            $hadirHariIniCount = $presensiQuery->count();

            $hadirPercentage = $pegawaiDijadwalkan > 0
                ? round(($hadirHariIniCount / $pegawaiDijadwalkan) * 100)
                : 0;

            // 3. Estimasi Payroll Bulan Ini
            $currentMonthStr = date('m-Y');

            $penggajianQuery = Penggajian::where('periode_bulan', $currentMonthStr);
            if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
                $penggajianQuery->whereHas('pegawai', function ($q) use ($user) {
                    $q->forUnit($user->unit_sekolah_id);
                });
            }
            $pengeluaranGaji = $penggajianQuery->sum('gaji_bersih');
            $isEstimasiPayroll = false;

            if ($pengeluaranGaji == 0) {
                $isEstimasiPayroll = true;
                $baseSalary = KomponenGaji::where('jenis', 'fixed')->sum('nilai_default');
                $pengeluaranGaji = $totalPegawai * $baseSalary;
            }

            // 4. Trend Kehadiran 7 Hari Terakhir
            $hariMap = [
                'Sunday' => 'Minggu',
                'Monday' => 'Senin',
                'Tuesday' => 'Selasa',
                'Wednesday' => 'Rabu',
                'Thursday' => 'Kamis',
                'Friday' => 'Jumat',
                'Saturday' => 'Sabtu',
            ];

            $startDate = Carbon::today('Asia/Jakarta')->subDays(6);
            $endDate = Carbon::today('Asia/Jakarta');

            $trendQuery = Presensi::whereBetween('tanggal', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->whereIn('status', ['hadir', 'telat'])
                ->selectRaw('tanggal, count(*) as total')
                ->groupBy('tanggal');

            if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
                $trendQuery->whereHas('pegawai', function ($q) use ($user) {
                    $q->forUnit($user->unit_sekolah_id);
                });
            }

            $trendData = $trendQuery->pluck('total', 'tanggal');

            $attendanceTrend = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = Carbon::today('Asia/Jakarta')->subDays($i);
                $dayName = $hariMap[$date->format('l')];
                $count = $trendData->get($date->format('Y-m-d'), 0);

                $attendanceTrend[] = [
                    'day' => $dayName,
                    'hadir' => $count,
                    'date' => $date->format('d/m'),
                ];
            }

            // 5. Kontrak Berakhir
            $kontrakQuery = Pegawai::where('status_kepegawaian', 'kontrak')
                ->whereNotNull('tanggal_akhir_kontrak')
                ->where('tanggal_akhir_kontrak', '<=', Carbon::today('Asia/Jakarta')->addDays(30))
                ->with('jabatans.unitSekolah');

            if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
                $kontrakQuery->forUnit($user->unit_sekolah_id);
            }
            $kontrakBerakhir = $kontrakQuery->get();

            // [FIX] convert to array before caching — serializable_classes=false blokir object
            $kontrakBerakhirArr = $kontrakBerakhir->toArray();

            // 6. Jadwal Hari Ini
            $jadwalHariIniQuery = Jadwal::with([
                'pegawai:id,nama_lengkap',
                'mataPelajaran:id,nama',
                'unitSekolah:id,nama,singkatan',
            ])
                ->where('hari', $hariIniIndo)
                ->orderBy('jam_mulai');

            if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
                $jadwalHariIniQuery->where('unit_sekolah_id', $user->unit_sekolah_id);
            }
            $jadwalHariIni = $jadwalHariIniQuery->get()->toArray();

            // 7. Presensi Hari Ini
            $presensiQuery = Presensi::with('pegawai:id,nama_lengkap')
                ->where('tanggal', $today)
                ->select(['pegawai_id', 'jadwal_id', 'jam_masuk', 'jam_keluar', 'status', 'lokasi_perlu_review']);

            if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
                $presensiQuery->where('unit_sekolah_id', $user->unit_sekolah_id);
            }
            $presensiHariIni = $presensiQuery->get()
                ->map(function ($p) {
                    $p->withoutAppends();
                    if ($p->pegawai) {
                        $p->setRelation('pegawai', $p->pegawai->withoutAppends());
                    }

                    return $p;
                })
                ->toArray();

            return [
                'totalPegawai' => $totalPegawai,
                'totalUnit' => $totalUnit,
                'pegawaiDijadwalkan' => $pegawaiDijadwalkan,
                'hadirHariIniCount' => $hadirHariIniCount,
                'hadirPercentage' => $hadirPercentage,
                'pengeluaranGaji' => $pengeluaranGaji,
                'isEstimasiPayroll' => $isEstimasiPayroll,
                'attendanceTrend' => $attendanceTrend,
                'kontrakBerakhir' => $kontrakBerakhirArr,
                'kontrakBerakhir_count' => $kontrakBerakhir->count(),
                'jadwalHariIni' => $jadwalHariIni,
                'presensiHariIni' => $presensiHariIni,
            ];
        });

        $totalPegawai = $admin['totalPegawai'];
        $totalUnit = $admin['totalUnit'];
        $pegawaiDijadwalkan = $admin['pegawaiDijadwalkan'];
        $hadirHariIniCount = $admin['hadirHariIniCount'];
        $hadirPercentage = $admin['hadirPercentage'];
        $pengeluaranGaji = $admin['pengeluaranGaji'];
        $isEstimasiPayroll = $admin['isEstimasiPayroll'];
        $attendanceTrend = $admin['attendanceTrend'];
        $kontrakBerakhir = $admin['kontrakBerakhir'];
        $kontrakBerakhirCount = $admin['kontrakBerakhir_count'];

        return inertia('Dashboard', [
            'roleType' => $roleType,
            'stats' => [
                'total_pegawai' => $totalPegawai,
                'total_unit' => $totalUnit,
                'hadir_hari_ini_count' => $hadirHariIniCount,
                'pegawai_dijadwalkan' => $pegawaiDijadwalkan,
                'hadir_percentage' => $hadirPercentage,
                'pengeluaran_gaji' => $pengeluaranGaji,
                'is_estimasi_payroll' => $isEstimasiPayroll,
                'kontrak_berakhir_count' => $kontrakBerakhirCount,
            ],
            'trends' => $attendanceTrend,
            'kontrakBerakhir' => $kontrakBerakhir,
            'jadwalHariIni' => $admin['jadwalHariIni'],
            'presensiHariIni' => $admin['presensiHariIni'],
        ]);
    }
}
