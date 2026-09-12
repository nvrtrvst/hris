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

    public function applyToImage(string $imageContent, array $data, int $maxDim = 640, int $quality = 80): string
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

        $estCardSize = max(80, min((int) round($width * 0.30), 180));
        $panelH = max($panelH, $estCardSize + $pad);
        $panelH = min($panelH, (int) round($height * 0.55));

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
     * Mini Location Card: map sebagai background, marker sebagai focal point,
     * info panel integrated di bagian bawah card.
     *
     * Struktur (180×180 px example):
     * ┌──────────────────────────────┐
     * │  card shadow + white border  │
     * │ ┌──────────────────────────┐ │
     * │ │  dimmed map background   │ │
     * │ │          ●               │ │
     * │ │  ┌────────────────────┐  │ │
     * │ │  │ LOKASI PRESENSI    │  │ │
     * │ │  │ -7.197, 107.895    │  │ │
     * │ │  │ ✓ Dalam radius     │  │ │
     * │ │  └────────────────────┘  │ │
     * │ └──────────────────────────┘ │
     * └──────────────────────────────┘
     */
    private function renderMap($img, array $data, int $panelTop, int $panelH): void
    {
        if (empty($data['latitude']) || empty($data['longitude'])) {
            return;
        }

        $lat = (float) $data['latitude'];
        $lng = (float) $data['longitude'];
        $zoom = 18;
        $tileSize = 256;

        // lat/lng → tile x,y (float)
        $n = pow(2, $zoom);
        $xt = ($lng + 180) / 360 * $n;
        $yt = (1 - log(tan(deg2rad($lat)) + 1 / cos(deg2rad($lat))) / M_PI) / 2 * $n;

        $tileX = (int) floor($xt);
        $tileY = (int) floor($yt);
        $fx = ($xt - $tileX) * $tileSize;
        $fy = ($yt - $tileY) * $tileSize;

        // Ukuran map container TETAP (30% lebar image, max 180px)
        $cardSize = (int) round(imagesx($img) * 0.30);
        $cardSize = max(80, min($cardSize, 180));

        // Fetch 4 tiles (2x2 grid) supaya GPS selalu dekat tengah
        $t0x = $fx < 128 ? $tileX - 1 : $tileX;
        $t0y = $fy < 128 ? $tileY - 1 : $tileY;

        $mosaicW = $tileSize * 2;
        $mosaicH = $tileSize * 2;
        $mosaic = imagecreatetruecolor($mosaicW, $mosaicH);
        $fallback = imagecolorallocate($mosaic, 230, 230, 230);
        imagefilledrectangle($mosaic, 0, 0, $mosaicW, $mosaicH, $fallback);

        $tilesLoaded = 0;
        for ($dy = 0; $dy < 2; $dy++) {
            for ($dx = 0; $dx < 2; $dx++) {
                $tileData = $this->fetchTile($zoom, $t0x + $dx, $t0y + $dy);
                if (empty($tileData)) {
                    continue;
                }
                $tileImg = @imagecreatefromstring($tileData);
                if ($tileImg === false) {
                    continue;
                }
                imagecopy($mosaic, $tileImg, $dx * $tileSize, $dy * $tileSize, 0, 0, $tileSize, $tileSize);
                imagedestroy($tileImg);
                $tilesLoaded++;
            }
        }

        if ($tilesLoaded === 0) {
            imagedestroy($mosaic);

            return;
        }

        // GPS position di mosaic (pasti dekat tengah 512x512)
        $gpsX = ($tileX - $t0x) * $tileSize + $fx;
        $gpsY = ($tileY - $t0y) * $tileSize + $fy;

        // Crop 80% centered on GPS
        $cropW = (int) round($mosaicW * 0.80);
        $cropH = (int) round($mosaicH * 0.80);
        $cropX = max(0, min((int) ($gpsX - $cropW / 2), $mosaicW - $cropW));
        $cropY = max(0, min((int) ($gpsY - $cropH / 2), $mosaicH - $cropH));

        $mapImg = imagecreatetruecolor($cropW, $cropH);
        imagecopyresampled($mapImg, $mosaic, 0, 0, $cropX, $cropY, $cropW, $cropH, $cropW, $cropH);
        imagedestroy($mosaic);

        // Marker position di cropped coords
        $dotX = (int) round($gpsX - $cropX);
        $dotY = (int) round($gpsY - $cropY);

        // Resize ke cardSize
        $finalMap = imagecreatetruecolor($cardSize, $cardSize);
        imagecopyresampled($finalMap, $mapImg, 0, 0, 0, 0, $cardSize, $cardSize, $cropW, $cropH);
        imagedestroy($mapImg);

        // ── CARD COMPOSITION ──
        $width = imagesx($img);
        $cardX = $width - $cardSize;
        $cardY = $panelTop + (int) round(($panelH - $cardSize) / 2);
        $cardY = max($panelTop, min($cardY, $panelTop + $panelH - $cardSize));

        $radius = 8;

        // Drop shadow
        $this->drawRoundedRectWithShadow($img, $cardX, $cardY, $cardSize, $cardSize, $radius, 2, 40);

        // Clip map ke rounded shape
        $this->applyRoundedClip($finalMap, 0, 0, $cardSize, $cardSize, $radius);

        // Composite map ke main photo
        imagecopy($img, $finalMap, $cardX, $cardY, 0, 0, $cardSize, $cardSize);
        imagedestroy($finalMap);

        // Marker di MAIN PHOTO — kecil, proporsional cardSize
        $mx = $cardX + (int) round($dotX * $cardSize / $cropW);
        $my = $cardY + (int) round($dotY * $cardSize / $cropH);

        $markerR = max(3, (int) round($cardSize * 0.02));
        $ringR = (int) round($markerR * 1.75);
        $shadowR = $ringR + 2;

        $origBlend = imagealphablending($img, true);

        // Shadow
        $shadowM = imagecolorallocatealpha($img, 0, 0, 0, 40);
        imagefilledellipse($img, $mx + 1, $my + 1, $shadowR * 2, $shadowR * 2, $shadowM);
        // White outer ring
        $whiteM = imagecolorallocate($img, 255, 255, 255);
        imagefilledellipse($img, $mx, $my, $ringR * 2, $ringR * 2, $whiteM);
        // Emerald center
        $brandM = imagecolorallocate($img, 16, 185, 129);
        imagefilledellipse($img, $mx, $my, $markerR * 2, $markerR * 2, $brandM);
    }

    /**
     * Draw rounded rectangle (frame) dengan drop shadow offset.
     * GD tidak punya rounded rect native — gambar 4 corner arcs + rectangles.
     */
    private function drawRoundedRectWithShadow($img, int $x, int $y, int $w, int $h, int $r, int $shadowOff, int $shadowAlpha): void
    {
        // Drop shadow layer
        $shadow = imagecolorallocatealpha($img, 0, 0, 0, $shadowAlpha);
        $this->filledRoundedRect($img,
            $x + $shadowOff, $y + $shadowOff,
            $w, $h, $r, $shadow
        );

        // White frame
        $white = imagecolorallocate($img, 255, 255, 255);
        $this->filledRoundedRect($img, $x, $y, $w, $h, $r, $white);
    }

    /**
     * Fill rounded rectangle area via 4 corner arcs + 2 rects + center rect.
     */
    private function filledRoundedRect($img, int $x, int $y, int $w, int $h, int $r, int $color): void
    {
        if ($r > $w / 2) {
            $r = (int) ($w / 2);
        }
        if ($r > $h / 2) {
            $r = (int) ($h / 2);
        }
        imagefilledrectangle($img, $x + $r, $y, $x + $w - $r, $y + $h, $color);
        imagefilledrectangle($img, $x, $y + $r, $x + $w, $y + $h - $r, $color);
        imagefilledellipse($img, $x + $r, $y + $r, $r * 2, $r * 2, $color);
        imagefilledellipse($img, $x + $w - $r, $y + $r, $r * 2, $r * 2, $color);
        imagefilledellipse($img, $x + $r, $y + $h - $r, $r * 2, $r * 2, $color);
        imagefilledellipse($img, $x + $w - $r, $y + $h - $r, $r * 2, $r * 2, $color);
    }

    /**
     * Apply rounded clip mask ke area canvas (temp map canvas).
     * Pixel di luar rounded shape dibuat fully transparent (alpha 127).
     */
    private function applyRoundedClip($img, int $x, int $y, int $w, int $h, int $r): void
    {
        $mask = imagecreatetruecolor($w, $h);
        imagealphablending($mask, false);
        imagesavealpha($mask, true);
        $transparent = imagecolorallocatealpha($mask, 0, 0, 0, 127);
        imagefilledrectangle($mask, 0, 0, $w, $h, $transparent);
        $opaque = imagecolorallocatealpha($mask, 0, 0, 0, 0);
        $this->filledRoundedRect($mask, 0, 0, $w, $h, $r, $opaque);

        // Enable alpha save di target image supaya transparent pixel komposite dengan benar
        imagealphablending($img, false);
        imagesavealpha($img, true);

        for ($py = 0; $py < $h; $py++) {
            for ($px = 0; $px < $w; $px++) {
                $maskAlpha = (imagecolorat($mask, $px, $py) >> 24) & 0x7F;
                if ($maskAlpha === 0) {
                    continue;
                }
                if ($maskAlpha === 127) {
                    $transparent2 = imagecolorallocatealpha($img, 0, 0, 0, 127);
                    imagesetpixel($img, $x + $px, $y + $py, $transparent2);

                    continue;
                }
                $origColor = imagecolorat($img, $x + $px, $y + $py);
                $origAlpha = ($origColor >> 24) & 0x7F;
                $newAlpha = max($origAlpha, $maskAlpha);
                $rChan = ($origColor >> 16) & 0xFF;
                $gChan = ($origColor >> 8) & 0xFF;
                $bChan = $origColor & 0xFF;
                $newColor = imagecolorallocatealpha($img, $rChan, $gChan, $bChan, $newAlpha);
                imagesetpixel($img, $x + $px, $y + $py, $newColor);
            }
        }
        imagedestroy($mask);

        // Restore default alpha mode untuk caller (text rendering butuh blending)
        imagealphablending($img, true);
    }

    /**
     * Integrated info panel di bagian bawah card.
     * 3 baris: LOKASI PRESENSI (title) + koordinat + status badge.
     * Full-width, white bg, rounded 6px.
     */
    private function drawMapInfoPanel($img, int $cardX, int $cardY, int $cardSize, array $data): void
    {
        $pad = max(6, (int) round($cardSize * 0.04));
        $innerPad = max(5, (int) round($cardSize * 0.03));

        $titleSz = max(7, (int) round($cardSize * 0.048));
        $coordSz = max(6, (int) round($cardSize * 0.040));
        $badgeSz = max(6, (int) round($cardSize * 0.038));

        // Data
        $lat = isset($data['latitude']) ? (float) $data['latitude'] : null;
        $lng = isset($data['longitude']) ? (float) $data['longitude'] : null;
        $coord = ($lat !== null && $lng !== null) ? sprintf('%.6f, %.6f', $lat, $lng) : '';

        $isTugasLuar = ! empty($data['is_tugas_luar']);
        $radiusStatus = $data['radius_status'] ?? true;
        $inside = ! $isTugasLuar && (bool) $radiusStatus;
        $badgeLabel = $isTugasLuar ? '' : ($inside ? '✓ Dalam radius' : '✕ Di luar radius');

        // Measure text widths
        $titleW = $this->textWidth($titleSz, 'LOKASI PRESENSI', $this->fontBold);
        $coordW = $coord ? $this->textWidth($coordSz, $coord, $this->fontRegular) : 0;
        $badgeW = $badgeLabel ? $this->textWidth($badgeSz, $badgeLabel, $this->fontBold) : 0;

        // Panel dimensions: full width minus card padding
        $panelW = $cardSize - $pad * 2;
        $lineGap = max(2, (int) round($cardSize * 0.012));

        $contentH = $titleSz + $lineGap; // title
        if ($coord) {
            $contentH += $coordSz + $lineGap;
        }
        if ($badgeLabel) {
            $contentH += $badgeSz;
        }
        $panelH = $contentH + $innerPad * 2;

        $panelX = $cardX + $pad;
        $panelY = $cardY + $cardSize - $panelH - $pad;

        // Background: white alpha-88, rounded 6px
        $bg = imagecolorallocatealpha($img, 255, 255, 255, 25);
        $panelRadius = max(4, (int) round($cardSize * 0.033));
        $this->filledRoundedRect($img, $panelX, $panelY, $panelW, $panelH, $panelRadius, $bg);

        // Colors
        $titleColor = imagecolorallocate($img, 5, 150, 105); // emerald-600
        $coordColor = imagecolorallocate($img, 51, 65, 85); // slate-600
        $badgeBg = $inside
            ? imagecolorallocatealpha($img, 209, 250, 229, 30) // emerald-100 alpha
            : imagecolorallocatealpha($img, 255, 228, 230, 30); // rose-100 alpha
        $badgeText = $inside
            ? imagecolorallocate($img, 6, 78, 59) // emerald-800
            : imagecolorallocate($img, 159, 18, 57); // rose-800

        // Draw lines
        $y = $panelY + $innerPad + $titleSz;
        $this->text($img, $titleSz, $panelX + $innerPad, $y, $titleColor, $this->fontBold, 'LOKASI PRESENSI');

        if ($coord) {
            $y += $titleSz + $lineGap;
            $this->text($img, $coordSz, $panelX + $innerPad, $y, $coordColor, $this->fontRegular, $coord);
        }

        if ($badgeLabel) {
            $y += $coordSz + $lineGap;
            $badgeX = $panelX + $innerPad;
            $badgeYPos = $y - $badgeSz;
            $badgeH = $badgeSz + (int) round($badgeSz * 0.35);
            $badgeTextW = $badgeW + (int) round($badgeSz * 0.8);

            // Badge pill background
            $this->filledRoundedRect($img, $badgeX, $badgeYPos, $badgeTextW, $badgeH, $badgeH / 2, $badgeBg);
            // Badge text
            $this->text($img, $badgeSz, $badgeX + (int) round($badgeSz * 0.4), $badgeYPos + $badgeH - (int) round($badgeH * 0.28), $badgeText, $this->fontBold, $badgeLabel);
        }
    }

    /**
     * Fetch tile dari CartoDB Positron (a/b/c subdomain), fallback ke OSM.
     * CartoDB Positron: light/clean theme, terms-of-use friendly untuk low-volume use.
     */
    private function fetchTile(int $zoom, int $tileX, int $tileY): ?string
    {
        $sources = [
            "https://a.tile.openstreetmap.org/{$zoom}/{$tileX}/{$tileY}.png",
            "https://b.tile.openstreetmap.org/{$zoom}/{$tileX}/{$tileY}.png",
            "https://c.tile.openstreetmap.org/{$zoom}/{$tileX}/{$tileY}.png",
        ];

        foreach ($sources as $url) {
            try {
                $response = Http::withHeaders([
                    'User-Agent' => 'HRIS-Yayasan/1.0 (presensi photo overlay)',
                ])->timeout(4)->get($url);
                if ($response->successful()) {
                    return $response->body();
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        return null;
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

    private function textWidth(float $size, string $text, string $font): int
    {
        $bbox = @imagettfbbox($size, 0, $font, $text);

        return $bbox ? (int) abs($bbox[2] - $bbox[0]) : 0;
    }
}
