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

        if (! Route::has('presensi.photo')) {
            $disk = config('filesystems.presensi_disk', 'presensi');

            return Storage::disk($disk)->url($path);
        }

        return route('presensi.photo', ['path' => $path]);
    }
}
