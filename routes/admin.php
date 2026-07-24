<?php

use App\Http\Controllers\BackupController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\JabatanController;
use App\Http\Controllers\JadwalController;
use App\Http\Controllers\KomponenGajiController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\MataPelajaranController;
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
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
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

    Route::get('/lengkapi-data', [ProfileController::class, 'editPegawai'])->name('lengkapi-data');
    Route::post('/lengkapi-data', [ProfileController::class, 'updatePegawai'])->name('lengkapi-data.store');

    // Pegawai — index/show bisa diakses staff, tapi create/edit/delete dikontrol di controller
    Route::resource('pegawai', PegawaiController::class)->middleware('throttle:60,1');

    // Jadwal — index bisa diakses staff (lihat jadwal sendiri)
    Route::get('jadwal', [JadwalController::class, 'index'])->name('jadwal.index');

    // Presensi — index bisa diakses staff (lihat presensi sendiri)
    Route::get('presensi', [PresensiController::class, 'index'])->name('presensi.index');

    // Penggajian — index/show bisa diakses staff (lihat slip gaji sendiri)
    Route::get('penggajian', [PenggajianController::class, 'index'])->name('penggajian.index');

    // ─── Rute Modul (Semua auth, perizinan diurus UI & Controller) ───
    // Pegawai Keuangan Khusus
    Route::get('pegawai/{pegawai}/keuangan', [PegawaiController::class, 'keuangan'])->name('pegawai.keuangan');
    Route::post('pegawai/{pegawai}/keuangan', [PegawaiController::class, 'updateKeuangan'])
        ->middleware('throttle:60,1')
        ->name('pegawai.keuangan.update');

    // Pegawai Import/Template
    Route::get('pegawai/template', [PegawaiController::class, 'downloadTemplate'])->name('pegawai.template');
    Route::post('pegawai/import', [PegawaiController::class, 'import'])
        ->middleware('throttle:30,1')
        ->name('pegawai.import');

    // Pegawai — NIK plaintext khusus HR/admin (gate view_sensitive_data)
    Route::get('pegawai/{pegawai}/nik-asli', [PegawaiController::class, 'nikAsli'])
        ->middleware('throttle:30,1')
        ->name('pegawai.nik-asli');

    // Unit Sekolah
    Route::resource('unit-sekolah', UnitSekolahController::class)->only(['index', 'create', 'store', 'edit', 'update'])->middleware('throttle:60,1');

    // Jadwal — create/store/generate/swap/destroy hanya admin
    Route::get('jadwal/create', [JadwalController::class, 'create'])->name('jadwal.create');
    Route::post('jadwal', [JadwalController::class, 'store'])
        ->middleware('throttle:60,1')
        ->name('jadwal.store');
    Route::get('jadwal/{jadwal}/edit', [JadwalController::class, 'edit'])->name('jadwal.edit');
    Route::put('jadwal/{jadwal}', [JadwalController::class, 'update'])
        ->middleware('throttle:60,1')
        ->name('jadwal.update');
    Route::post('jadwal/generate', [JadwalController::class, 'generate'])
        ->middleware('throttle:30,1')
        ->name('jadwal.generate');
    Route::post('jadwal/swap', [JadwalController::class, 'swap'])
        ->middleware('throttle:30,1')
        ->name('jadwal.swap');
    Route::delete('jadwal/{jadwal}', [JadwalController::class, 'destroy'])
        ->middleware('throttle:60,1')
        ->name('jadwal.destroy');

    // Presensi — create/store/update hanya admin
    Route::get('presensi/create', [PresensiController::class, 'create'])->name('presensi.create');
    Route::post('presensi', [PresensiController::class, 'store'])
        ->middleware('throttle:60,1')
        ->name('presensi.store');
    Route::put('presensi/{presensi}', [PresensiController::class, 'update'])
        ->middleware('throttle:60,1')
        ->name('presensi.update');
    Route::post('presensi/{presensi}/approve-lembur', [PresensiController::class, 'approveLembur'])
        ->middleware('throttle:60,1')
        ->name('presensi.approveLembur');
    Route::post('presensi/{presensi}/reject-lembur', [PresensiController::class, 'rejectLembur'])
        ->middleware('throttle:60,1')
        ->name('presensi.rejectLembur');

    // Komponen Gaji Matrix
    Route::get('komponen-gaji/matrix', [PegawaiKomponenController::class, 'matrix'])->name('komponen-gaji.matrix');
    Route::post('komponen-gaji/matrix', [PegawaiKomponenController::class, 'updateMatrix'])
        ->middleware('throttle:60,1')
        ->name('komponen-gaji.matrix.update');

    // Komponen Gaji
    Route::resource('komponen-gaji', KomponenGajiController::class)->middleware('throttle:60,1');
    Route::resource('skala-masa-bakti', SkalaMasaBaktiController::class)->only(['index', 'store', 'destroy'])->middleware('throttle:60,1');

    // Atur Nominal Spesifik per Pegawai (Manual / Import Excel)
    Route::get('komponen-gaji/{komponen_gaji}/pegawai', [PegawaiKomponenController::class, 'index'])->name('komponen-gaji.pegawai.index');
    Route::post('komponen-gaji/{komponen_gaji}/pegawai/batch', [PegawaiKomponenController::class, 'updateBatch'])
        ->middleware('throttle:60,1')
        ->name('komponen-gaji.pegawai.batch');
    Route::get('komponen-gaji/{komponen_gaji}/pegawai/template', [PegawaiKomponenController::class, 'downloadTemplate'])->name('komponen-gaji.pegawai.template');
    Route::post('komponen-gaji/{komponen_gaji}/pegawai/import', [PegawaiKomponenController::class, 'import'])
        ->middleware('throttle:30,1')
        ->name('komponen-gaji.pegawai.import');

    // Penggajian — Run Payroll Wizard
    Route::get('penggajian/run', [PenggajianController::class, 'indexRun'])->name('penggajian.run');
    Route::post('penggajian/run/init', [PenggajianController::class, 'createDraft'])
        ->middleware('throttle:30,1')
        ->name('penggajian.run.init');
    Route::get('penggajian/run/draft/{month}/{year}', [PenggajianController::class, 'worksheet'])->name('penggajian.run.worksheet');
    Route::get('penggajian/run/draft/{month}/{year}/data', [PenggajianController::class, 'getWorksheetData'])->name('penggajian.run.worksheet_data');
    Route::post('penggajian/run/draft/{month}/{year}/save', [PenggajianController::class, 'saveWorksheet'])
        ->middleware('throttle:60,1')
        ->name('penggajian.run.worksheet_save');
    Route::post('penggajian/run/draft/{month}/{year}/finalize', [PenggajianController::class, 'finalizeWorksheet'])
        ->middleware('throttle:30,1')
        ->name('penggajian.run.worksheet_finalize');

    Route::delete('penggajian/destroy-period', [PenggajianController::class, 'destroyPeriod'])
        ->middleware('throttle:30,1')
        ->name('penggajian.destroy_period');
    Route::delete('penggajian/{id}', [PenggajianController::class, 'destroy'])
        ->middleware('throttle:60,1')
        ->name('penggajian.destroy');

    // Pengajuan Izin — approve/reject hanya admin
    Route::resource('pengajuan-izin', PengajuanIzinController::class)->only(['index']);
    Route::post('/pengajuan-izin/{id}/approve', [PengajuanIzinController::class, 'approve'])
        ->middleware('throttle:60,1')
        ->name('pengajuan-izin.approve');
    Route::post('/pengajuan-izin/{id}/reject', [PengajuanIzinController::class, 'reject'])
        ->middleware('throttle:60,1')
        ->name('pengajuan-izin.reject');

    // Laporan
    Route::get('laporan', [LaporanController::class, 'index'])->name('laporan.index');
    Route::get('laporan/preview', [LaporanController::class, 'preview'])->name('laporan.preview');
    Route::get('laporan/presensi', [LaporanController::class, 'exportPresensi'])->name('laporan.presensi');
    Route::get('laporan/penggajian', [LaporanController::class, 'exportPenggajian'])->name('laporan.penggajian');
    Route::get('laporan/lemburan', [LaporanController::class, 'exportLemburan'])->name('laporan.lemburan');

    // Rute slip gaji ditempatkan setelah rute admin agar tidak tabrakan dengan /penggajian/run
    Route::get('penggajian/{id}', [PenggajianController::class, 'show'])->name('penggajian.show');

    Route::middleware('can:manage_users')->group(function () {
        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserManagementController::class, 'create'])->name('users.create');
        Route::post('/users', [UserManagementController::class, 'store'])
            ->middleware('throttle:60,1')
            ->name('users.store');
        Route::get('/users/{user}/edit', [UserManagementController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserManagementController::class, 'update'])
            ->middleware('throttle:60,1')
            ->name('users.update');
    });

    Route::resource('roles', RoleManagementController::class)
        ->except(['show'])
        ->middleware(['can:manage_roles', 'throttle:60,1']);

    // Pengaturan Master (Superadmin)
    Route::middleware('can:manage_master_data')->group(function () {
        Route::get('backup', [BackupController::class, 'index'])->name('backup.index');
        Route::get('backup/download', [BackupController::class, 'download'])
            ->middleware('throttle:10,1')
            ->name('backup.download');
        Route::resource('mata-pelajaran', MataPelajaranController::class)->only(['index', 'store', 'destroy'])->middleware('throttle:60,1');
        Route::resource('jabatan', JabatanController::class)->except(['show', 'create', 'edit'])->middleware('throttle:60,1');
    });
});

require __DIR__.'/auth.php';
