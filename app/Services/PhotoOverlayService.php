<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class PhotoOverlayService
{
    private string $fontRegular;

    private string $fontBold;

    public function __construct(?string $fontRegular = null, ?string $fontBold = null)
    {
        $base = public_path('fonts');
        $this->fontRegular = $fontRegular ?? $base.'/Figtree-Regular.ttf';
        $this->fontBold = $fontBold ?? $base.'/Figtree-Bold.ttf';
    }

    public function applyToImage(string $imageContent, array $data, int $maxDim = 640, int $quality = 60): string
    {
        $img = @imagecreatefromstring($imageContent);
        if ($img === false) {
            throw new \InvalidArgumentException('Gagal memproses gambar untuk overlay.');
        }

        try {
            $this->renderOverlay($img, $data);
            $img = $this->resize($img, $maxDim);
            ob_start();
            imagewebp($img, null, $quality);

            return ob_get_clean();
        } finally {
            imagedestroy($img);
        }
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

    private function renderOverlay($img, array $data): void
    {
        $width = imagesx($img);
        $height = imagesy($img);

        $pad = max(12, (int) round($width * 0.030));
        $titleSz = max(14, (int) round($width * 0.032));
        $bodySz = max(11, (int) round($width * 0.024));
        $smallSz = max(9, (int) round($width * 0.020));

        $white = imagecolorallocate($img, 255, 255, 255);
        $gray = imagecolorallocate($img, 200, 212, 220);
        $emerald = imagecolorallocate($img, 110, 231, 183);
        $amber = imagecolorallocate($img, 252, 211, 77);
        $badgeText = imagecolorallocate($img, 17, 24, 39);

        $isLembur = ! empty($data['is_lembur']);
        $labelColor = $isLembur ? $amber : $emerald;

        // ── Panel bawah: label + waktu + info ──
        $lines = [];
        $labelText = $data['label'] ?? 'BUKTI PRESENSI';
        $timeText = ! empty($data['time']) ? '  |  '.$data['time'] : '';
        $lines[] = [$titleSz, $labelText.$timeText, $labelColor, true];
        $nameUnit = '';
        if (! empty($data['pegawai'])) {
            $nameUnit .= $data['pegawai'];
        }
        if (! empty($data['unit'])) {
            $nameUnit .= ' - '.$data['unit'];
        }
        if ($nameUnit) {
            $lines[] = [$bodySz, $nameUnit, $white, true];
        }
        if (! empty($data['date'])) {
            $lines[] = [$smallSz, $data['date'], $gray, false];
        }
        $coordLine = '';
        if (! empty($data['coordinates'])) {
            $coordLine .= $data['coordinates'];
        }
        if (! empty($data['accuracy'])) {
            $coordLine .= ($coordLine ? ' | ' : '').'Akurasi: '.$data['accuracy'];
        }
        if ($coordLine) {
            $lines[] = [$smallSz, $coordLine, $white, false];
        }
        $alamat = $this->buildAlamat($data);
        if ($alamat) {
            $lines[] = [$smallSz, $alamat, $white, false];
        }

        $gap = 4;
        $panelH = $pad * 2;
        foreach ($lines as [$sz, $txt, $col, $bold]) {
            $panelH += $sz + $gap;
        }
        $panelH = min($panelH, (int) round($height * 0.42));
        $panelTop = $height - $panelH;

        $panel = imagecolorallocatealpha($img, 0, 0, 0, 80);
        imagefilledrectangle($img, 0, $panelTop, $width, $height, $panel);

        $y = $panelTop + $pad + $titleSz;
        foreach ($lines as [$sz, $txt, $col, $bold]) {
            $font = $bold ? $this->fontBold : $this->fontRegular;
            $this->text($img, $sz, $pad, $y, $col, $font, $txt);
            $y += $sz + $gap;
        }

        // Map thumbnail di pojok kanan panel
        $this->renderMap($img, $data, $panelTop, $panelH);
    }

    /**
     * Render peta kecil OSM di pojok kanan bawah panel overlay.
     */
    private function renderMap($img, array $data, int $panelTop, int $panelH): void
    {
        if (empty($data['latitude']) || empty($data['longitude'])) {
            return;
        }

        $lat = (float) $data['latitude'];
        $lng = (float) $data['longitude'];
        $zoom = 16;
        $tileSize = 256;

        // lat/lng → tile x,y (float)
        $n = pow(2, $zoom);
        $xt = ($lng + 180) / 360 * $n;
        $yt = (1 - log(tan(deg2rad($lat)) + 1 / cos(deg2rad($lat))) / M_PI) / 2 * $n;

        $tileX = (int) floor($xt);
        $tileY = (int) floor($yt);

        // Fetch 3×3 tiles around center untuk area lebih lebar
        $mapSize = (int) round(imagesx($img) * 0.30);
        $mapSize = max(80, min($mapSize, 180));

        $tileUrl = 'https://tile.openstreetmap.org/'.$zoom.'/'.$tileX.'/'.$tileY.'.png';
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'HRIS-Yayasan/1.0 (presensi photo overlay)',
            ])->timeout(5)->get($tileUrl);
            $tileData = $response->successful() ? $response->body() : null;
        } catch (\Throwable $e) {
            $tileData = null;
        }

        if (empty($tileData)) {
            return;
        }

        $tileImg = @imagecreatefromstring($tileData);
        if ($tileImg === false) {
            return;
        }

        // Crop tile ke posisi fraction
        $fx = ($xt - $tileX) * $tileSize;
        $fy = ($yt - $tileY) * $tileSize;

        $cropW = (int) round($tileSize * 0.6);
        $cropH = (int) round($tileSize * 0.6);
        $cropX = max(0, min((int) ($fx - $cropW / 2), $tileSize - $cropW));
        $cropY = max(0, min((int) ($fy - $cropH / 2), $tileSize - $cropH));

        $mapImg = imagecreatetruecolor($cropW, $cropH);
        imagecopyresampled($mapImg, $tileImg, 0, 0, $cropX, $cropY, $cropW, $cropH, $cropW, $cropH);
        imagedestroy($tileImg);

        // Draw marker di tengah
        $dotR = max(4, (int) round($mapSize * 0.04));
        $dotX = (int) round($cropW * (($fx - $cropX) / $cropW));
        $dotY = (int) round($cropH * (($fy - $cropY) / $cropH));
        $red = imagecolorallocate($mapImg, 239, 68, 68);
        imagefilledellipse($mapImg, $dotX, $dotY, $dotR * 2, $dotR * 2, $red);
        $white2 = imagecolorallocate($mapImg, 255, 255, 255);
        imagefilledellipse($mapImg, $dotX, $dotY, $dotR, $dotR, $white2);

        // Resize ke mapSize
        $finalMap = imagecreatetruecolor($mapSize, $mapSize);
        imagecopyresampled($finalMap, $mapImg, 0, 0, 0, 0, $mapSize, $mapSize, $cropW, $cropH);
        imagedestroy($mapImg);

        // Position: pojok kanan bawah panel, menempel sisi kanan
        $width = imagesx($img);
        $mapX = $width - $mapSize;
        $mapY = $panelTop + (int) round(($panelH - $mapSize) / 2);
        $mapY = max($panelTop, min($mapY, $panelTop + $panelH - $mapSize));

        // White border
        $border = 2;
        $white3 = imagecolorallocate($img, 255, 255, 255);
        imagefilledrectangle($img, $mapX - $border, $mapY - $border, $mapX + $mapSize + $border, $mapY + $mapSize + $border, $white3);

        imagecopy($img, $finalMap, $mapX, $mapY, 0, 0, $mapSize, $mapSize);
        imagedestroy($finalMap);
    }

    private function buildAlamat(array $data): ?string
    {
        $kecamatan = $data['kecamatan'] ?? null;
        $kelurahan = $data['kelurahan'] ?? null;
        $kabupaten = $data['kabupaten'] ?? null;
        if (! $kecamatan && ! $kelurahan && ! $kabupaten) {
            return null;
        }

        $parts = [];
        if ($kecamatan) {
            $parts[] = 'Kec. '.$kecamatan;
        }
        if ($kelurahan) {
            $parts[] = 'Kel. '.$kelurahan;
        }
        if ($kabupaten) {
            $parts[] = 'Kab. '.$kabupaten;
        }

        return implode(' | ', $parts);
    }

    private function textRight($img, float $size, int $rightX, int $y, $color, string $font, string $text): void
    {
        $bbox = @imagettfbbox($size, 0, $font, $text);
        $w = $bbox ? (int) abs($bbox[2] - $bbox[0]) : 0;
        $x = $rightX - $w;
        $shadow = imagecolorallocatealpha($img, 0, 0, 0, 80);
        for ($dx = -1; $dx <= 1; $dx++) {
            for ($dy = -1; $dy <= 1; $dy++) {
                if ($dx === 0 && $dy === 0) {
                    continue;
                }
                @imagettftext($img, $size, 0, $x + $dx, $y + $dy, $shadow, $font, $text);
            }
        }
        @imagettftext($img, $size, 0, $x, $y, $color, $font, $text);
    }

    private function text($img, float $size, int $x, int $y, $color, string $font, string $text): void
    {
        $shadow = imagecolorallocatealpha($img, 0, 0, 0, 80);
        for ($dx = -1; $dx <= 1; $dx++) {
            for ($dy = -1; $dy <= 1; $dy++) {
                if ($dx === 0 && $dy === 0) {
                    continue;
                }
                @imagettftext($img, $size, 0, $x + $dx, $y + $dy, $shadow, $font, $text);
            }
        }
        @imagettftext($img, $size, 0, $x, $y, $color, $font, $text);
    }
}
