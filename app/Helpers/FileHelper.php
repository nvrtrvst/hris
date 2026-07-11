<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;

class FileHelper
{
    public static function fotoUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        // Legacy: stored with /storage/ prefix
        if (str_starts_with($path, '/storage/')) {
            return asset($path);
        }

        // New: relative path — resolve via storage disk
        $disk = config('filesystems.image_disk', 'public');
        return Storage::disk($disk)->url($path);
    }
}
