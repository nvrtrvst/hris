<?php

use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\MobileAuthController;
use App\Http\Controllers\MobileController;
use App\Http\Controllers\MobileIzinController;
use App\Http\Controllers\ProfileController;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Mobile PWA Routes untuk Pegawai (presensi.nuurulmuttaqiin)
Route::get('/login', [MobileAuthController::class, 'create'])
    ->middleware('guest:web_mobile')->name('presensi.login');
Route::post('/login', [MobileAuthController::class, 'store'])
    ->middleware('guest:web_mobile')->name('presensi.login.store');
Route::post('/logout', [MobileAuthController::class, 'destroy'])
    ->middleware('auth:web_mobile')->name('presensi.logout');

Route::middleware('auth:web_mobile')->group(function () {
    Route::get('/', [MobileController::class, 'dashboard'])->name('presensi.dashboard');
    Route::get('/jadwal', [MobileController::class, 'jadwal'])->name('presensi.jadwal');
    Route::get('/jadwal/kelas', [MobileController::class, 'kelasUnit'])
        ->middleware('throttle:30,1')->name('presensi.jadwal.kelas');
    Route::get('/jadwal/siswa', [MobileController::class, 'siswaKelas'])
        ->middleware('throttle:30,1')->name('presensi.jadwal.siswa');
    Route::get('/riwayat', [MobileController::class, 'riwayat'])->name('presensi.riwayat');

    // Rute Izin Mobile
    Route::get('/izin', [MobileIzinController::class, 'index'])->name('presensi.izin.index');
    Route::get('/izin/create', [MobileIzinController::class, 'create'])->name('presensi.izin.create');
    Route::post('/izin', [MobileIzinController::class, 'store'])->name('presensi.izin.store');

    Route::get('/absen', [MobileController::class, 'absen'])->name('presensi.absen');
    Route::post('/absen', [MobileController::class, 'storeAbsen'])
        ->middleware('throttle:10,1') // 10 requests per minute per user
        ->name('presensi.absen.store');
    Route::get('/profile', function (Request $request) {
        $request->user()->pegawai?->load('units', 'jabatans');

        return Inertia::render('Mobile/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
        ]);
    })->name('presensi.profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('presensi.profile.update');
    Route::put('/password', [PasswordController::class, 'update'])->name('presensi.password.update');

    Route::get('/lengkapi-data', [ProfileController::class, 'editPegawai'])->name('presensi.lengkapi-data');
    Route::post('/lengkapi-data', [ProfileController::class, 'updatePegawai'])->name('presensi.lengkapi-data.store');
});
