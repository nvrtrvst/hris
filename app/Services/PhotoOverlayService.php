<?php

namespace App\Services;

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

        // ── Badge atas: label + waktu (kesan "stempel" jelas) ──
        $vpad = max(8, (int) round($width * 0.018));
        $badgeH = $titleSz + $vpad * 2;
        imagefilledrectangle($img, 0, 0, $width, $badgeH, $labelColor);
        $labelText = $data['label'] ?? 'BUKTI PRESENSI';
        $this->text($img, $titleSz, $pad, $vpad + $titleSz, $badgeText, $this->fontBold, $labelText);
        if (! empty($data['time'])) {
            $this->textRight($img, $titleSz, $width - $pad, $vpad + $titleSz, $badgeText, $this->fontBold, $data['time']);
        }

        // ── Panel bawah: hierarki rapi + alamat (kecamatan/kelurahan) ──
        $lines = [];
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
        imagefilledrectangle($img, 0, $panelTop, $width, $panelTop + 3, $labelColor);

        $y = $panelTop + $pad;
        foreach ($lines as [$sz, $txt, $col, $bold]) {
            $font = $bold ? $this->fontBold : $this->fontRegular;
            $this->text($img, $sz, $pad, $y, $col, $font, $txt);
            $y += $sz + $gap;
        }
    }

    private function buildAlamat(array $data): ?string
    {
        $kecamatan = $data['kecamatan'] ?? null;
        $kelurahan = $data['kelurahan'] ?? null;
        if (! $kecamatan && ! $kelurahan) {
            return null;
        }

        $parts = [];
        if ($kecamatan) {
            $parts[] = 'Kec. '.$kecamatan;
        }
        if ($kelurahan) {
            $parts[] = 'Kel. '.$kelurahan;
        }

        return implode(' • ', $parts);
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
