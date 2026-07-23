<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageUploadService
{
    /**
     * Simpan base64 image ke disk sebagai WebP.
     * Kalo $pegawai disediakan: folder/{id}_{slug_nama}/{timestamp}.webp
     * Kalo null: folder/{uuid}.webp
     *
     * @param  string  $folder  Subfolder (mis. 'presensi', 'presensi/lembur', 'izin')
     * @param  array|null  $overlayData  Server-side photo burn-in
     * @param  array|null  $pegawai  ['id' => int, 'nama' => string] untuk folder per-pegawai
     * @return string Path relatif
     */
    public function storeBase64(string $base64, string $folder = 'presensi', ?array $overlayData = null, int $maxBytes = 5 * 1024 * 1024, ?array $pegawai = null): string
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

        if ($overlayData !== null) {
            $decoded = app(PhotoOverlayService::class)->applyToImage($decoded, $overlayData);
        } else {
            $img = @imagecreatefromstring($decoded);
            if ($img !== false) {
                $img = $this->resize($img, 640);
                ob_start();
                imagewebp($img, null, 60);
                $decoded = ob_get_clean();
                imagedestroy($img);
            }
        }

        $disk = config('filesystems.image_disk', 'public');

        if ($pegawai) {
            $sub = $pegawai['id'].'_'.Str::slug($pegawai['nama'], '_');
            $actualFolder = $folder.'/'.$sub;
            $fileName = $actualFolder.'/'.now()->format('Y-m-d_H-i-s').'.webp';
        } else {
            $fileName = $folder.'/'.Str::uuid().'.webp';
        }

        Storage::disk($disk)->put($fileName, $decoded);

        return $fileName;
    }

    private function resize($img, int $maxDim)
    {
        $w = imagesx($img);
        $h = imagesy($img);
        if ($w <= $maxDim && $h <= $maxDim) {
            return $img;
        }

        $ratio = min($maxDim / $w, $maxDim / $h);
        $nw = (int) round($w * $ratio);
        $nh = (int) round($h * $ratio);

        $resized = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($resized, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($img);

        return $resized;
    }
}
