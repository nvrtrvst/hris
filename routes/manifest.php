<?php

use Illuminate\Support\Facades\Route;

Route::get('/manifest.json', function () {
    $isMobileDomain = request()->getHost() === config('domains.mobile');
    $startUrl = $isMobileDomain ? '/' : '/mobile';
    $scope = $isMobileDomain ? '/' : '/mobile';

    return response()->json([
        'name' => 'Presensi HRIS Yayasan',
        'short_name' => 'Presensi',
        'description' => 'Presensi, izin, dan jadwal pegawai Yayasan.',
        'start_url' => $startUrl,
        'scope' => $scope,
        'display' => 'standalone',
        'orientation' => 'portrait',
        'background_color' => '#0F3D3E',
        'theme_color' => '#0F3D3E',
        'icons' => [
            [
                'src' => '/icons/icon-192.png',
                'sizes' => '192x192',
                'type' => 'image/png',
                'purpose' => 'any',
            ],
            [
                'src' => '/icons/icon-512.png',
                'sizes' => '512x512',
                'type' => 'image/png',
                'purpose' => 'any',
            ],
        ],
    ])->header('Content-Type', 'application/json')
      ->header('Cache-Control', 'no-cache, no-store, must-revalidate');
});
