<?php

namespace App\Http\Controllers;

use App\Models\Pegawai;
use App\Models\UnitSekolah;
use App\Models\Presensi;
use App\Models\Penggajian;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        
        $roleType = 'Staff';
        if ($user->role === 'superadmin') $roleType = 'Super Admin';
        elseif ($user->role === 'admin_unit') $roleType = 'Admin Unit';

        if ($roleType === 'Staff') {
            $pegawai = Pegawai::where('user_id', $user->id)->first();
            
            $hadirBulanIni = 0;
            $jadwalBulanIni = 0;
            
            if ($pegawai) {
                $hadirBulanIni = Presensi::where('pegawai_id', $pegawai->id)
                    ->whereMonth('tanggal', Carbon::now('Asia/Jakarta')->month)
                    ->whereYear('tanggal', Carbon::now('Asia/Jakarta')->year)
                    ->whereIn('status', ['hadir', 'telat'])
                    ->count();
                    
                // Hitung kasar jadwal kerja
                $jadwalBulanIni = 22; // Asumsi 22 hari kerja untuk Staff (bisa disesuaikan nanti)
            }

            return inertia('DashboardSelfService', [
                'stats' => [
                    'hadir_bulan_ini' => $hadirBulanIni,
                    'jadwal_bulan_ini' => $jadwalBulanIni,
                ]
            ]);
        }

        // Logic for Admin / HR / Unit Dashboard
        $today = Carbon::today('Asia/Jakarta');
        
        // 1. Total Pegawai Aktif
        $pegawaiQuery = Pegawai::where('status_aktif', 'aktif');
        if ($user->role === 'admin_unit') {
            $pegawaiQuery->where('unit_sekolah_id', $user->unit_sekolah_id);
        }
        $totalPegawai = $pegawaiQuery->count();
        $totalUnit = UnitSekolah::count();
        
        // 2. Kehadiran Hari Ini (Real vs Jadwal)
        $hariIniIndo = \App\Helpers\HariHelper::hariIniIndo();
        
        $jadwalQuery = \App\Models\Jadwal::where('hari', $hariIniIndo);
        if ($user->role === 'admin_unit') {
            $jadwalQuery->where('unit_sekolah_id', $user->unit_sekolah_id);
        }
        $pegawaiDijadwalkan = $jadwalQuery->distinct('pegawai_id')->count('pegawai_id');
            
        $presensiQuery = Presensi::where('tanggal', $today)->whereIn('status', ['hadir', 'telat']);
        if ($user->role === 'admin_unit') {
            $presensiQuery->whereHas('pegawai', function($q) use ($user) {
                $q->where('unit_sekolah_id', $user->unit_sekolah_id);
            });
        }
        $hadirHariIniCount = $presensiQuery->count();
            
        $hadirPercentage = $pegawaiDijadwalkan > 0 
            ? round(($hadirHariIniCount / $pegawaiDijadwalkan) * 100) 
            : 0;

        // 3. Estimasi Payroll Bulan Ini
        $currentMonthStr = date('m-Y');
        
        $penggajianQuery = Penggajian::where('periode_bulan', $currentMonthStr);
        if ($user->role === 'admin_unit') {
            $penggajianQuery->whereHas('pegawai', function($q) use ($user) {
                $q->where('unit_sekolah_id', $user->unit_sekolah_id);
            });
        }
        $pengeluaranGaji = $penggajianQuery->sum('gaji_bersih');
        $isEstimasiPayroll = false;
        
        if ($pengeluaranGaji == 0) {
            $isEstimasiPayroll = true;
            $baseSalary = \App\Models\KomponenGaji::where('jenis', 'fixed')->sum('nilai_default');
            $pengeluaranGaji = $totalPegawai * $baseSalary; 
        }

        // 4. Trend Kehadiran 7 Hari Terakhir
        $attendanceTrend = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today('Asia/Jakarta')->subDays($i);
            $dayName = $hariMap[$date->format('l')];
            
            $trendQuery = Presensi::where('tanggal', $date->format('Y-m-d'))
                ->whereIn('status', ['hadir', 'telat']);
            if ($user->role === 'admin_unit') {
                $trendQuery->whereHas('pegawai', function($q) use ($user) {
                    $q->where('unit_sekolah_id', $user->unit_sekolah_id);
                });
            }
            $count = $trendQuery->count();
                
            $attendanceTrend[] = [
                'day' => $dayName,
                'hadir' => $count,
                'date' => $date->format('d/m')
            ];
        }

        // 5. Kontrak Berakhir
        $kontrakQuery = Pegawai::where('status_kepegawaian', 'kontrak')
            ->whereNotNull('tanggal_akhir_kontrak')
            ->where('tanggal_akhir_kontrak', '<=', Carbon::today('Asia/Jakarta')->addDays(30))
            ->with('jabatans.unitSekolah');
            
        if ($user->role === 'admin_unit') {
            $kontrakQuery->where('unit_sekolah_id', $user->unit_sekolah_id);
        }
        $kontrakBerakhir = $kontrakQuery->get();

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
                'kontrak_berakhir_count' => $kontrakBerakhir->count(),
            ],
            'trends' => $attendanceTrend,
            'kontrakBerakhir' => $kontrakBerakhir,
        ]);
    }
}
