<?php

use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\MobileAuthController;
use App\Http\Controllers\MobileController;
use App\Http\Controllers\MobileGajiController;
use App\Http\Controllers\MobileIzinController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PresensiController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PushSubscriptionController;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Mobile PWA Routes untuk Pegawai (presensi.nuurulmuttaqiin)
Route::get('/login', [MobileAuthController::class, 'create'])
    ->middleware('guest:web_mobile')->name('presensi.login');
Route::post('/login', [MobileAuthController::class, 'store'])
    ->middleware('guest:web_mobile')->middleware('throttle:5,1')->name('presensi.login.store');
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
    Route::get('/pengumuman', [MobileController::class, 'pengumuman'])->name('presensi.pengumuman');

    // Gaji Mobile (F1)
    Route::get('/gaji', [MobileGajiController::class, 'index'])->name('presensi.gaji.index');
    Route::get('/gaji/{id}', [MobileGajiController::class, 'show'])->name('presensi.gaji.show');

    // Notifikasi Mobile (F2)
    Route::get('/notifikasi', [NotificationController::class, 'indexMobile'])->name('presensi.notifikasi.index');
    Route::post('/notifikasi/{id}/read', [NotificationController::class, 'markRead'])->middleware('throttle:60,1')->name('presensi.notifikasi.read');
    Route::post('/notifikasi/read-all', [NotificationController::class, 'markAllRead'])->middleware('throttle:60,1')->name('presensi.notifikasi.read-all');
    Route::post('/notifikasi/{id}/archive', [NotificationController::class, 'archive'])->middleware('throttle:60,1')->name('presensi.notifikasi.archive');
    Route::post('/notifikasi/{id}/restore', [NotificationController::class, 'restore'])->middleware('throttle:60,1')->name('presensi.notifikasi.restore');

    // Rute Izin Mobile — /create SEBELUM /{pengajuan} supaya gak ketangkap wildcard
    Route::get('/izin', [MobileIzinController::class, 'index'])->name('presensi.izin.index');
    Route::get('/izin/create', [MobileIzinController::class, 'create'])->name('presensi.izin.create');
    Route::get('/izin/{pengajuan}', [MobileIzinController::class, 'show'])->name('presensi.izin.show');
    Route::post('/izin', [MobileIzinController::class, 'store'])
        ->middleware('throttle:10,1')->name('presensi.izin.store');

    Route::get('/absen', [MobileController::class, 'absen'])->name('presensi.absen');
    Route::post('/absen', [MobileController::class, 'storeAbsen'])
        ->middleware('throttle:10,1') // 10 requests per minute per user
        ->name('presensi.absen.store');
    Route::post('/absen-tetap', [MobileController::class, 'storeAbsenTetap'])
        ->middleware('throttle:10,1')->name('presensi.absen.tetap');
    Route::post('/tap-jadwal', [MobileController::class, 'tapJadwal'])
        ->middleware('throttle:30,1')->name('presensi.absen.tap');

    Route::post('/tugas-luar/{presensi}/bukti', [PresensiController::class, 'storeBuktiTugasLuar'])
        ->middleware('throttle:10,1')->name('presensi.tugas-luar.bukti');

    // Web Push Subscription (notifikasi reminder presensi & status izin)
    Route::post('/push/subscribe', [PushSubscriptionController::class, 'subscribe'])
        ->middleware('throttle:20,1')->name('presensi.push.subscribe');
    Route::post('/push/unsubscribe', [PushSubscriptionController::class, 'unsubscribe'])
        ->middleware('throttle:20,1')->name('presensi.push.unsubscribe');
    Route::get('/push/subscriptions', [PushSubscriptionController::class, 'index'])
        ->middleware('throttle:60,1')->name('presensi.push.subscriptions');
    Route::get('/profile', function (Request $request) {
        $pegawai = $request->user()->pegawai;
        if ($pegawai) {
            $pegawai->load('units', 'jabatans');
            // Profile.jsx menampilkan sisa_cuti — append eksplisit (P2).
            $pegawai->loadCutiInfo();
        }

        return Inertia::render('Mobile/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
        ]);
    })->name('presensi.profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('presensi.profile.update');
    Route::patch('/profile/data', [ProfileController::class, 'updatePegawaiData'])->name('presensi.profile.data.update');
    Route::put('/password', [PasswordController::class, 'update'])->name('presensi.password.update');

    Route::get('/lengkapi-data', [ProfileController::class, 'editPegawai'])->name('presensi.lengkapi-data');
    Route::post('/lengkapi-data', [ProfileController::class, 'updatePegawai'])->name('presensi.lengkapi-data.store');
});
