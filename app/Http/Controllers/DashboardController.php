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
                ->with('jabatans.unitSekolah', 'pengajuanIzins');

            if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
                $kontrakQuery->forUnit($user->unit_sekolah_id);
            }
            $kontrakBerakhir = $kontrakQuery->get();

            // [FIX] convert to array before caching — serializable_classes=false blokir object
            $kontrakBerakhirArr = $kontrakBerakhir->toArray();

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
        ]);
    }
}
