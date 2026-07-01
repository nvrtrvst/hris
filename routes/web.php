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
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('pegawai/template', [\App\Http\Controllers\PegawaiController::class, 'downloadTemplate'])->name('pegawai.template');
    Route::post('pegawai/import', [\App\Http\Controllers\PegawaiController::class, 'import'])->name('pegawai.import');
    Route::resource('pegawai', \App\Http\Controllers\PegawaiController::class);
    Route::resource('unit-sekolah', \App\Http\Controllers\UnitSekolahController::class)->only(['index', 'edit', 'update']);
    Route::post('jadwal/generate', [\App\Http\Controllers\JadwalController::class, 'generate'])->name('jadwal.generate');
    Route::post('jadwal/swap', [\App\Http\Controllers\JadwalController::class, 'swap'])->name('jadwal.swap');
    Route::resource('jadwal', \App\Http\Controllers\JadwalController::class);
    Route::resource('presensi', \App\Http\Controllers\PresensiController::class)->only(['index', 'create', 'store', 'update']);
    Route::resource('komponen-gaji', \App\Http\Controllers\KomponenGajiController::class);
    Route::get('penggajian', [\App\Http\Controllers\PenggajianController::class, 'index'])->name('penggajian.index');
    Route::post('penggajian/generate', [\App\Http\Controllers\PenggajianController::class, 'generate'])->name('penggajian.generate');
    Route::post('penggajian/finalize-period', [\App\Http\Controllers\PenggajianController::class, 'finalizePeriod'])->name('penggajian.finalize_period');
    Route::post('/penggajian/{id}/finalize', [\App\Http\Controllers\PenggajianController::class, 'finalize'])->name('penggajian.finalize');

    Route::resource('pengajuan-izin', \App\Http\Controllers\PengajuanIzinController::class)->only(['index']);
    Route::post('/pengajuan-izin/{id}/approve', [\App\Http\Controllers\PengajuanIzinController::class, 'approve'])->name('pengajuan-izin.approve');
    Route::post('/pengajuan-izin/{id}/reject', [\App\Http\Controllers\PengajuanIzinController::class, 'reject'])->name('pengajuan-izin.reject');

    Route::delete('penggajian/destroy-period', [\App\Http\Controllers\PenggajianController::class, 'destroyPeriod'])->name('penggajian.destroy_period');
    Route::delete('penggajian/{id}', [\App\Http\Controllers\PenggajianController::class, 'destroy'])->name('penggajian.destroy');
    Route::get('penggajian/{id}', [\App\Http\Controllers\PenggajianController::class, 'show'])->name('penggajian.show');

    // Laporan
    Route::get('laporan', [\App\Http\Controllers\LaporanController::class, 'index'])->name('laporan.index');
    Route::get('laporan/preview', [\App\Http\Controllers\LaporanController::class, 'preview'])->name('laporan.preview');
    Route::get('laporan/presensi', [\App\Http\Controllers\LaporanController::class, 'exportPresensi'])->name('laporan.presensi');
    Route::get('laporan/penggajian', [\App\Http\Controllers\LaporanController::class, 'exportPenggajian'])->name('laporan.penggajian');
    Route::get('laporan/lemburan', [\App\Http\Controllers\LaporanController::class, 'exportLemburan'])->name('laporan.lemburan');

    // Backup
    Route::get('backup', [\App\Http\Controllers\BackupController::class, 'index'])->name('backup.index');
    Route::get('backup/download', [\App\Http\Controllers\BackupController::class, 'download'])->name('backup.download');
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
