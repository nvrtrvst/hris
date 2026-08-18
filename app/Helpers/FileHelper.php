<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

class FileHelper
{
    public static function fotoUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, '/storage/')) {
            return asset($path);
        }

        // Aset publik (mis. logo unit) disimpan di image_disk (public) — serve
        // langsung via URL publik, bukan route presensi.photo yang membaca disk
        // presensi (private). Cek keberadaan file agar tidak salah arah.
        $imageDisk = config('filesystems.image_disk', 'public');
        if ($imageDisk !== 'presensi' && Storage::disk($imageDisk)->exists($path)) {
            return Storage::disk($imageDisk)->url($path);
        }

        if (! Route::has('presensi.photo')) {
            $disk = config('filesystems.presensi_disk', 'presensi');

            return Storage::disk($disk)->url($path);
        }

        return route('presensi.photo', ['path' => $path]);
    }
}
