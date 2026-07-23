<?php

use App\Http\Controllers\PresensiPhotoController;
use Illuminate\Support\Facades\Route;

/*
 * ── Subdomain Routing ───────────────────────────────────────────────
 * Production: admin di ADMIN_DOMAIN, presensi/mobile di MOBILE_DOMAIN.
 * Local dev : domain env kosong → fallback ke path-based (/ dan /mobile).
 *
 *   simsdm.nuurulmuttaqiin.or.id → portal Admin (routes/admin.php)
 *   presensi.nuurulmuttaqiin   → portal Mobile PWA (routes/mobile.php)
 */

// Global protected photo serve — satu route untuk kedua portal
Route::get('presensi/photo/{path}', [PresensiPhotoController::class, 'show'])
    ->where('path', '.*')
    ->middleware('auth:web_admin,web_mobile')
    ->name('presensi.photo');

$adminDomain = config('domains.admin');
$mobileDomain = config('domains.mobile');

// ─── Admin Portal ───────────────────────────────────────────────────
if ($adminDomain) {
    Route::domain($adminDomain)->group(function () {
        require __DIR__.'/admin.php';
    });
} else {
    Route::group([], function () {
        require __DIR__.'/admin.php';
    });
}

// ─── Mobile / Presensi Portal ───────────────────────────────────────
if ($mobileDomain) {
    Route::domain($mobileDomain)->group(function () {
        require __DIR__.'/mobile.php';
    });
} else {
    Route::group(['prefix' => 'mobile'], function () {
        require __DIR__.'/mobile.php';
    });
}
