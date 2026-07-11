<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageUploadService
{
    /**
     * Simpan base64 image ke disk public dengan nama UUID (hindari path traversal
     * dan simpan di luar web root yang dapat dieksekusi).
     *
     * @param  string  $base64  String base64 (format: data:image/jpeg;base64,....)
     * @param  string  $folder  Subfolder di dalam storage/app/public (mis. 'presensi')
     * @param  int  $maxBytes  Batas ukuran decode (default 5 MB)
     * @return string Path relatif terhadap storage/app/public (mis. 'presensi/uuid.jpg')
     *
     * @throws \InvalidArgumentException jika format/mime tidak valid atau terlalu besar
     */
    public function storeBase64(string $base64, string $folder = 'presensi', int $maxBytes = 5 * 1024 * 1024): string
    {
        if (! preg_match('/^data:image\/(\w+);base64,/', $base64, $matches)) {
            throw new \InvalidArgumentException('Format gambar base64 tidak valid.');
        }

        $extension = strtolower($matches[1]);
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'])) {
            throw new \InvalidArgumentException('Tipe gambar tidak diizinkan.');
        }

        $data = substr($base64, strpos($base64, ',') + 1);
        $decoded = base64_decode($data, true);
        if ($decoded === false) {
            throw new \InvalidArgumentException('Gagal mendekode gambar.');
        }

        if (strlen($decoded) > $maxBytes) {
            throw new \InvalidArgumentException('Ukuran gambar melebihi batas maksimum.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($decoded);
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'])) {
            throw new \InvalidArgumentException('Konten bukan gambar yang valid.');
        }

        $fileName = $folder.'/'.Str::uuid().'.'.$extension;
        Storage::disk('public')->put($fileName, $decoded);

        return $fileName;
    }
}
