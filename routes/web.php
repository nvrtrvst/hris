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

    // Presensi — index bisa diakses staff (lihat presensi sendiri)
    Route::get('presensi', [\App\Http\Controllers\PresensiController::class, 'index'])->name('presensi.index');

    // Penggajian — index/show bisa diakses staff (lihat slip gaji sendiri)
    Route::get('penggajian', [\App\Http\Controllers\PenggajianController::class, 'index'])->name('penggajian.index');

    // ─── Rute Modul (Semua auth, perizinan diurus UI & Controller) ───
        // Pegawai Keuangan Khusus
        Route::get('pegawai/{pegawai}/keuangan', [\App\Http\Controllers\PegawaiController::class, 'keuangan'])->name('pegawai.keuangan');
        Route::post('pegawai/{pegawai}/keuangan', [\App\Http\Controllers\PegawaiController::class, 'updateKeuangan'])->name('pegawai.keuangan.update');
        
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
        
        // Komponen Gaji Matrix
        Route::get('komponen-gaji/matrix', [\App\Http\Controllers\PegawaiKomponenController::class, 'matrix'])->name('komponen-gaji.matrix');
        Route::post('komponen-gaji/matrix', [\App\Http\Controllers\PegawaiKomponenController::class, 'updateMatrix'])->name('komponen-gaji.matrix.update');

        // Komponen Gaji
        Route::resource('komponen-gaji', \App\Http\Controllers\KomponenGajiController::class);
        Route::resource('skala-masa-bakti', \App\Http\Controllers\SkalaMasaBaktiController::class)->only(['index', 'store', 'destroy']);
        
        // Atur Nominal Spesifik per Pegawai (Manual / Import Excel)
        Route::get('komponen-gaji/{komponen_gaji}/pegawai', [\App\Http\Controllers\PegawaiKomponenController::class, 'index'])->name('komponen-gaji.pegawai.index');
        Route::post('komponen-gaji/{komponen_gaji}/pegawai/batch', [\App\Http\Controllers\PegawaiKomponenController::class, 'updateBatch'])->name('komponen-gaji.pegawai.batch');
        Route::get('komponen-gaji/{komponen_gaji}/pegawai/template', [\App\Http\Controllers\PegawaiKomponenController::class, 'downloadTemplate'])->name('komponen-gaji.pegawai.template');
        Route::post('komponen-gaji/{komponen_gaji}/pegawai/import', [\App\Http\Controllers\PegawaiKomponenController::class, 'import'])->name('komponen-gaji.pegawai.import');
        
        
        // Penggajian — Run Payroll Wizard
        Route::get('penggajian/run', [\App\Http\Controllers\PenggajianController::class, 'indexRun'])->name('penggajian.run');
        Route::post('penggajian/run/init', [\App\Http\Controllers\PenggajianController::class, 'createDraft'])->name('penggajian.run.init');
        Route::get('penggajian/run/draft/{month}/{year}', [\App\Http\Controllers\PenggajianController::class, 'worksheet'])->name('penggajian.run.worksheet');
        Route::get('penggajian/run/draft/{month}/{year}/data', [\App\Http\Controllers\PenggajianController::class, 'getWorksheetData'])->name('penggajian.run.worksheet_data');
        Route::post('penggajian/run/draft/{month}/{year}/save', [\App\Http\Controllers\PenggajianController::class, 'saveWorksheet'])->name('penggajian.run.worksheet_save');
        Route::post('penggajian/run/draft/{month}/{year}/finalize', [\App\Http\Controllers\PenggajianController::class, 'finalizeWorksheet'])->name('penggajian.run.worksheet_finalize');

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

    // Rute slip gaji ditempatkan setelah rute admin agar tidak tabrakan dengan /penggajian/run
    Route::get('penggajian/{id}', [\App\Http\Controllers\PenggajianController::class, 'show'])->name('penggajian.show');

    Route::get('/users', [App\Http\Controllers\UserManagementController::class, 'index'])->name('users.index');
    Route::get('/users/create', [App\Http\Controllers\UserManagementController::class, 'create'])->name('users.create');
    Route::post('/users', [App\Http\Controllers\UserManagementController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [App\Http\Controllers\UserManagementController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [App\Http\Controllers\UserManagementController::class, 'update'])->name('users.update');

    Route::resource('roles', \App\Http\Controllers\RoleManagementController::class)->except(['show']);

    // Pengaturan Master (Superadmin)
    Route::middleware('can:manage_master_data')->group(function () {
        Route::get('backup', [\App\Http\Controllers\BackupController::class, 'index'])->name('backup.index');
        Route::get('backup/download', [\App\Http\Controllers\BackupController::class, 'download'])->name('backup.download');
    });
});

// Debug: cek semua cookies yg dikirim browser
Route::get('/debug/cookies', function (\Illuminate\Http\Request $req) {
    $adminRemember = 'remember_web_admin_'.sha1(\Illuminate\Auth\SessionGuard::class);
    $mobileRemember = 'remember_web_mobile_'.sha1(\Illuminate\Auth\SessionGuard::class);
    return response()->json([
        'uri' => $req->getRequestUri(),
        'all_cookies' => collect($req->cookies->all())->keys()->values(),
        'cookie_details' => collect($req->cookies->all())->map(fn($v, $k) => [
            'name' => $k,
            'truncated' => strlen($v) > 40 ? substr($v, 0, 40).'...' : $v,
            'is_admin_remember' => $k === $adminRemember,
            'is_mobile_remember' => $k === $mobileRemember,
        ])->values(),
        'auth_web_admin' => auth('web_admin')->check(),
        'auth_web_mobile' => auth('web_mobile')->check(),
        'guard_config' => config('auth.defaults.guard'),
        'session_cookie' => config('session.cookie'),
        'session_id' => session()->getId(),
    ]);
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

// Dev login (CSRF excluded, hapus setelah debug selesai)
Route::post('/dev-login', function (\Illuminate\Http\Request $request) {
    $credentials = $request->only('login', 'password');
    $field = filter_var($credentials['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'no_induk';
    if (Auth::attempt([$field => $credentials['login'], 'password' => $credentials['password']], true)) {
        $request->session()->regenerate();
        return redirect('/debug/cookies');
    }
    return response()->json(['error' => 'Login failed'], 401);
})->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);

require __DIR__.'/auth.php';
