<?php

use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\JadwalController;
use App\Http\Controllers\KomponenGajiController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\MobileAuthController;
use App\Http\Controllers\MobileController;
use App\Http\Controllers\MobileIzinController;
use App\Http\Controllers\PegawaiController;
use App\Http\Controllers\PegawaiKomponenController;
use App\Http\Controllers\PengajuanIzinController;
use App\Http\Controllers\PenggajianController;
use App\Http\Controllers\PresensiController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleManagementController;
use App\Http\Controllers\SkalaMasaBaktiController;
use App\Http\Controllers\UnitSekolahController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect('/mobile');
});

// Rute Desktop (Manajemen & Portal Staff)
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth:web_admin', 'verified'])
    ->name('dashboard');

Route::middleware('auth:web_admin')->group(function () {
    // Rute yang bisa diakses SEMUA user yang login (termasuk Staff)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Pegawai — index/show bisa diakses staff, tapi create/edit/delete dikontrol di controller
    Route::resource('pegawai', PegawaiController::class);

    // Jadwal — index bisa diakses staff (lihat jadwal sendiri)
    Route::get('jadwal', [JadwalController::class, 'index'])->name('jadwal.index');

    // Presensi — index bisa diakses staff (lihat presensi sendiri)
    Route::get('presensi', [PresensiController::class, 'index'])->name('presensi.index');

    // Penggajian — index/show bisa diakses staff (lihat slip gaji sendiri)
    Route::get('penggajian', [PenggajianController::class, 'index'])->name('penggajian.index');

    // ─── Rute Modul (Semua auth, perizinan diurus UI & Controller) ───
    // Pegawai Keuangan Khusus
    Route::get('pegawai/{pegawai}/keuangan', [PegawaiController::class, 'keuangan'])->name('pegawai.keuangan');
    Route::post('pegawai/{pegawai}/keuangan', [PegawaiController::class, 'updateKeuangan'])->name('pegawai.keuangan.update');

    // Pegawai Import/Template
    Route::get('pegawai/template', [PegawaiController::class, 'downloadTemplate'])->name('pegawai.template');
    Route::post('pegawai/import', [PegawaiController::class, 'import'])->name('pegawai.import');

    // Unit Sekolah
    Route::resource('unit-sekolah', UnitSekolahController::class)->only(['index', 'create', 'store', 'edit', 'update']);

    // Jadwal — create/store/generate/swap/destroy hanya admin
    Route::get('jadwal/create', [JadwalController::class, 'create'])->name('jadwal.create');
    Route::post('jadwal', [JadwalController::class, 'store'])->name('jadwal.store');
    Route::get('jadwal/{jadwal}/edit', [JadwalController::class, 'edit'])->name('jadwal.edit');
    Route::put('jadwal/{jadwal}', [JadwalController::class, 'update'])->name('jadwal.update');
    Route::post('jadwal/generate', [JadwalController::class, 'generate'])->name('jadwal.generate');
    Route::post('jadwal/swap', [JadwalController::class, 'swap'])->name('jadwal.swap');
    Route::delete('jadwal/{jadwal}', [JadwalController::class, 'destroy'])->name('jadwal.destroy');

    // Presensi — create/store/update hanya admin
    Route::get('presensi/create', [PresensiController::class, 'create'])->name('presensi.create');
    Route::post('presensi', [PresensiController::class, 'store'])->name('presensi.store');
    Route::put('presensi/{presensi}', [PresensiController::class, 'update'])->name('presensi.update');
    Route::post('presensi/{presensi}/approve-lembur', [PresensiController::class, 'approveLembur'])->name('presensi.approveLembur');
    Route::post('presensi/{presensi}/reject-lembur', [PresensiController::class, 'rejectLembur'])->name('presensi.rejectLembur');

    // Komponen Gaji Matrix
    Route::get('komponen-gaji/matrix', [PegawaiKomponenController::class, 'matrix'])->name('komponen-gaji.matrix');
    Route::post('komponen-gaji/matrix', [PegawaiKomponenController::class, 'updateMatrix'])->name('komponen-gaji.matrix.update');

    // Komponen Gaji
    Route::resource('komponen-gaji', KomponenGajiController::class);
    Route::resource('skala-masa-bakti', SkalaMasaBaktiController::class)->only(['index', 'store', 'destroy']);

    // Atur Nominal Spesifik per Pegawai (Manual / Import Excel)
    Route::get('komponen-gaji/{komponen_gaji}/pegawai', [PegawaiKomponenController::class, 'index'])->name('komponen-gaji.pegawai.index');
    Route::post('komponen-gaji/{komponen_gaji}/pegawai/batch', [PegawaiKomponenController::class, 'updateBatch'])->name('komponen-gaji.pegawai.batch');
    Route::get('komponen-gaji/{komponen_gaji}/pegawai/template', [PegawaiKomponenController::class, 'downloadTemplate'])->name('komponen-gaji.pegawai.template');
    Route::post('komponen-gaji/{komponen_gaji}/pegawai/import', [PegawaiKomponenController::class, 'import'])->name('komponen-gaji.pegawai.import');

    // Penggajian — Run Payroll Wizard
    Route::get('penggajian/run', [PenggajianController::class, 'indexRun'])->name('penggajian.run');
    Route::post('penggajian/run/init', [PenggajianController::class, 'createDraft'])->name('penggajian.run.init');
    Route::get('penggajian/run/draft/{month}/{year}', [PenggajianController::class, 'worksheet'])->name('penggajian.run.worksheet');
    Route::get('penggajian/run/draft/{month}/{year}/data', [PenggajianController::class, 'getWorksheetData'])->name('penggajian.run.worksheet_data');
    Route::post('penggajian/run/draft/{month}/{year}/save', [PenggajianController::class, 'saveWorksheet'])->name('penggajian.run.worksheet_save');
    Route::post('penggajian/run/draft/{month}/{year}/finalize', [PenggajianController::class, 'finalizeWorksheet'])->name('penggajian.run.worksheet_finalize');

    Route::delete('penggajian/destroy-period', [PenggajianController::class, 'destroyPeriod'])->name('penggajian.destroy_period');
    Route::delete('penggajian/{id}', [PenggajianController::class, 'destroy'])->name('penggajian.destroy');

    // Pengajuan Izin — approve/reject hanya admin
    Route::resource('pengajuan-izin', PengajuanIzinController::class)->only(['index']);
    Route::post('/pengajuan-izin/{id}/approve', [PengajuanIzinController::class, 'approve'])->name('pengajuan-izin.approve');
    Route::post('/pengajuan-izin/{id}/reject', [PengajuanIzinController::class, 'reject'])->name('pengajuan-izin.reject');

    // Laporan
    Route::get('laporan', [LaporanController::class, 'index'])->name('laporan.index');
    Route::get('laporan/preview', [LaporanController::class, 'preview'])->name('laporan.preview');
    Route::get('laporan/presensi', [LaporanController::class, 'exportPresensi'])->name('laporan.presensi');
    Route::get('laporan/penggajian', [LaporanController::class, 'exportPenggajian'])->name('laporan.penggajian');
    Route::get('laporan/lemburan', [LaporanController::class, 'exportLemburan'])->name('laporan.lemburan');

    // Rute slip gaji ditempatkan setelah rute admin agar tidak tabrakan dengan /penggajian/run
    Route::get('penggajian/{id}', [PenggajianController::class, 'show'])->name('penggajian.show');

    Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserManagementController::class, 'create'])->name('users.create');
    Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [UserManagementController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');

    Route::resource('roles', RoleManagementController::class)->except(['show']);

    // Pengaturan Master (Superadmin)
    Route::middleware('can:manage_master_data')->group(function () {
        Route::get('backup', [BackupController::class, 'index'])->name('backup.index');
        Route::get('backup/download', [BackupController::class, 'download'])->name('backup.download');
        Route::resource('mata-pelajaran', MataPelajaranController::class)->only(['index', 'store', 'destroy']);
    });
});

// Debug: cek semua cookies yg dikirim browser
Route::get('/debug/cookies', function (Request $req) {
    $adminRemember = 'remember_web_admin_'.sha1(SessionGuard::class);
    $mobileRemember = 'remember_web_mobile_'.sha1(SessionGuard::class);

    return response()->json([
        'uri' => $req->getRequestUri(),
        'all_cookies' => collect($req->cookies->all())->keys()->values(),
        'cookie_details' => collect($req->cookies->all())->map(fn ($v, $k) => [
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
    Route::get('/login', [MobileAuthController::class, 'create'])
        ->middleware('guest:web_mobile')->name('mobile.login');
    Route::post('/login', [MobileAuthController::class, 'store'])
        ->middleware('guest:web_mobile')->name('mobile.login.store');
    Route::post('/logout', [MobileAuthController::class, 'destroy'])
        ->middleware('auth:web_mobile')->name('mobile.logout');

    Route::middleware('auth:web_mobile')->group(function () {
        Route::get('/', [MobileController::class, 'dashboard'])->name('mobile.dashboard');
        Route::get('/jadwal', [MobileController::class, 'jadwal'])->name('mobile.jadwal');
        Route::get('/jadwal/siswa', [MobileController::class, 'siswaKelas'])->name('mobile.jadwal.siswa');
        Route::post('/jadwal/siswa/absen', [MobileController::class, 'siswaAbsen'])->name('mobile.jadwal.siswa.absen');
        Route::post('/jadwal/siswa/absen-batch', [MobileController::class, 'siswaAbsenBatch'])->name('mobile.jadwal.siswa.absen-batch');
        Route::get('/riwayat', [MobileController::class, 'riwayat'])->name('mobile.riwayat');

        // Rute Izin Mobile
        Route::get('/izin', [MobileIzinController::class, 'index'])->name('mobile.izin.index');
        Route::get('/izin/create', [MobileIzinController::class, 'create'])->name('mobile.izin.create');
        Route::post('/izin', [MobileIzinController::class, 'store'])->name('mobile.izin.store');

        Route::get('/absen', [MobileController::class, 'absen'])->name('mobile.absen');
        Route::post('/absen', [MobileController::class, 'storeAbsen'])->name('mobile.absen.store');
        Route::get('/profile', function (Request $request) {
            $request->user()->pegawai?->load('units', 'jabatans');

            return Inertia::render('Mobile/Profile', [
                'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
                'status' => session('status'),
            ]);
        })->name('mobile.profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('mobile.profile.update');
        Route::put('/password', [PasswordController::class, 'update'])->name('mobile.password.update');
    });
});

require __DIR__.'/auth.php';
