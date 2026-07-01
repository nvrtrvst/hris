<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('login');
});

// Rute Desktop (Manajemen & Portal Staff)
Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])
    ->middleware(['auth:web_admin', 'verified'])
    ->name('dashboard');

Route::middleware('auth:web_admin')->group(function () {
    // Rute yang bisa diakses SEMUA user yang login (termasuk Staff)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Pegawai — index/show bisa diakses staff, tapi create/edit/delete dikontrol di controller
    Route::resource('pegawai', \App\Http\Controllers\PegawaiController::class);
    
    // Jadwal — index bisa diakses staff (lihat jadwal sendiri)
    Route::get('jadwal', [\App\Http\Controllers\JadwalController::class, 'index'])->name('jadwal.index');
    Route::get('jadwal/{jadwal}', [\App\Http\Controllers\JadwalController::class, 'show'])->name('jadwal.show');

    // Presensi — index bisa diakses staff (lihat presensi sendiri)
    Route::get('presensi', [\App\Http\Controllers\PresensiController::class, 'index'])->name('presensi.index');

    // Penggajian — index/show bisa diakses staff (lihat slip gaji sendiri)
    Route::get('penggajian', [\App\Http\Controllers\PenggajianController::class, 'index'])->name('penggajian.index');
    Route::get('penggajian/{id}', [\App\Http\Controllers\PenggajianController::class, 'show'])->name('penggajian.show');

    // ─── Rute KHUSUS Admin (superadmin + admin_unit) ───
    Route::middleware('role:superadmin,admin_unit')->group(function () {
        // Pegawai Import/Template
        Route::get('pegawai/template', [\App\Http\Controllers\PegawaiController::class, 'downloadTemplate'])->name('pegawai.template');
        Route::post('pegawai/import', [\App\Http\Controllers\PegawaiController::class, 'import'])->name('pegawai.import');
        
        // Unit Sekolah
        Route::resource('unit-sekolah', \App\Http\Controllers\UnitSekolahController::class)->only(['index', 'edit', 'update']);
        
        // Jadwal — create/store/generate/swap/destroy hanya admin
        Route::get('jadwal/create', [\App\Http\Controllers\JadwalController::class, 'create'])->name('jadwal.create');
        Route::post('jadwal', [\App\Http\Controllers\JadwalController::class, 'store'])->name('jadwal.store');
        Route::post('jadwal/generate', [\App\Http\Controllers\JadwalController::class, 'generate'])->name('jadwal.generate');
        Route::post('jadwal/swap', [\App\Http\Controllers\JadwalController::class, 'swap'])->name('jadwal.swap');
        Route::delete('jadwal/{jadwal}', [\App\Http\Controllers\JadwalController::class, 'destroy'])->name('jadwal.destroy');
        
        // Presensi — create/store/update hanya admin
        Route::get('presensi/create', [\App\Http\Controllers\PresensiController::class, 'create'])->name('presensi.create');
        Route::post('presensi', [\App\Http\Controllers\PresensiController::class, 'store'])->name('presensi.store');
        Route::put('presensi/{presensi}', [\App\Http\Controllers\PresensiController::class, 'update'])->name('presensi.update');
        
        // Komponen Gaji
        Route::resource('komponen-gaji', \App\Http\Controllers\KomponenGajiController::class);
        
        // Penggajian — generate/finalize/destroy hanya admin
        Route::post('penggajian/generate', [\App\Http\Controllers\PenggajianController::class, 'generate'])->name('penggajian.generate');
        Route::post('penggajian/finalize-period', [\App\Http\Controllers\PenggajianController::class, 'finalizePeriod'])->name('penggajian.finalize_period');
        Route::post('/penggajian/{id}/finalize', [\App\Http\Controllers\PenggajianController::class, 'finalize'])->name('penggajian.finalize');
        Route::delete('penggajian/destroy-period', [\App\Http\Controllers\PenggajianController::class, 'destroyPeriod'])->name('penggajian.destroy_period');
        Route::delete('penggajian/{id}', [\App\Http\Controllers\PenggajianController::class, 'destroy'])->name('penggajian.destroy');

        // Pengajuan Izin — approve/reject hanya admin
        Route::resource('pengajuan-izin', \App\Http\Controllers\PengajuanIzinController::class)->only(['index']);
        Route::post('/pengajuan-izin/{id}/approve', [\App\Http\Controllers\PengajuanIzinController::class, 'approve'])->name('pengajuan-izin.approve');
        Route::post('/pengajuan-izin/{id}/reject', [\App\Http\Controllers\PengajuanIzinController::class, 'reject'])->name('pengajuan-izin.reject');

        // Laporan
        Route::get('laporan', [\App\Http\Controllers\LaporanController::class, 'index'])->name('laporan.index');
        Route::get('laporan/preview', [\App\Http\Controllers\LaporanController::class, 'preview'])->name('laporan.preview');
        Route::get('laporan/presensi', [\App\Http\Controllers\LaporanController::class, 'exportPresensi'])->name('laporan.presensi');
        Route::get('laporan/penggajian', [\App\Http\Controllers\LaporanController::class, 'exportPenggajian'])->name('laporan.penggajian');
        Route::get('laporan/lemburan', [\App\Http\Controllers\LaporanController::class, 'exportLemburan'])->name('laporan.lemburan');
    });

    // ─── Rute KHUSUS Super Admin ───
    Route::middleware('role:superadmin')->group(function () {
        Route::get('backup', [\App\Http\Controllers\BackupController::class, 'index'])->name('backup.index');
        Route::get('backup/download', [\App\Http\Controllers\BackupController::class, 'download'])->name('backup.download');
    });
});

// Mobile PWA Routes untuk Pegawai
Route::prefix('mobile')->group(function () {
    Route::get('/login', [\App\Http\Controllers\MobileAuthController::class, 'create'])
        ->middleware('guest:web_mobile')->name('mobile.login');
    Route::post('/login', [\App\Http\Controllers\MobileAuthController::class, 'store'])
        ->middleware('guest:web_mobile')->name('mobile.login.store');
    Route::post('/logout', [\App\Http\Controllers\MobileAuthController::class, 'destroy'])
        ->middleware('auth:web_mobile')->name('mobile.logout');

    Route::middleware('auth:web_mobile')->group(function () {
        Route::get('/', [\App\Http\Controllers\MobileController::class, 'dashboard'])->name('mobile.dashboard');
        Route::get('/jadwal', [\App\Http\Controllers\MobileController::class, 'jadwal'])->name('mobile.jadwal');
        Route::get('/riwayat', [\App\Http\Controllers\MobileController::class, 'riwayat'])->name('mobile.riwayat');

        // Rute Izin Mobile
        Route::get('/izin', [\App\Http\Controllers\MobileIzinController::class, 'index'])->name('mobile.izin.index');
        Route::get('/izin/create', [\App\Http\Controllers\MobileIzinController::class, 'create'])->name('mobile.izin.create');
        Route::post('/izin', [\App\Http\Controllers\MobileIzinController::class, 'store'])->name('mobile.izin.store');

        Route::get('/absen', [\App\Http\Controllers\MobileController::class, 'absen'])->name('mobile.absen');
        Route::post('/absen', [\App\Http\Controllers\MobileController::class, 'storeAbsen'])->name('mobile.storeAbsen');
        Route::get('/profile', [ProfileController::class, 'edit'])->name('mobile.profile.edit');
    });
});

require __DIR__.'/auth.php';
