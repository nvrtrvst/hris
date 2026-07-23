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
        $lineH = max(12, (int) round($width * 0.028));

        $titleSz = max(14, (int) round($width * 0.030));
        $bodySz = max(11, (int) round($width * 0.024));
        $smallSz = max(9, (int) round($width * 0.020));

        $lines = 3;
        if (! empty($data['coordinates'])) $lines++;
        $panelH = min($lines * ($titleSz + 8) + $pad * 2, (int) round($height * 0.20));
        $panelTop = $height - $panelH;

        $panel = imagecolorallocatealpha($img, 0, 0, 0, 75);
        $white = imagecolorallocate($img, 255, 255, 255);
        $gray = imagecolorallocate($img, 200, 212, 220);
        $emerald = imagecolorallocate($img, 110, 231, 183);
        $amber = imagecolorallocate($img, 252, 211, 77);

        imagefilledrectangle($img, 0, $panelTop, $width, $height, $panel);

        $isLembur = ! empty($data['is_lembur']);
        $labelColor = $isLembur ? $amber : $emerald;

        $y = $panelTop + $pad;

        $labelText = $data['label'] ?? 'BUKTI PRESENSI';
        $timeText = $data['time'] ?? '';
        $line = $labelText.($timeText ? '  |  '.$timeText : '');
        $this->text($img, $titleSz, $pad, $y, $labelColor, $this->fontBold, $line);
        $y += $titleSz + 6;

        $nameUnit = '';
        if (! empty($data['pegawai'])) {
            $nameUnit .= $data['pegawai'];
        }
        if (! empty($data['unit'])) {
            $nameUnit .= ' - '.$data['unit'];
        }
        if ($nameUnit) {
            $this->text($img, $bodySz, $pad, $y, $white, $this->fontBold, $nameUnit);
            $y += $bodySz + 4;
        }

        $dateText = $data['date'] ?? '';
        if ($dateText) {
            $this->text($img, $smallSz, $pad, $y, $gray, $this->fontRegular, $dateText);
            $y += $smallSz + 4;
        }

        $coordLine = '';
        if (! empty($data['coordinates'])) {
            $coordLine .= $data['coordinates'];
        }
        if (! empty($data['accuracy'])) {
            $coordLine .= ($coordLine ? ' | ' : '').'Akurasi: '.$data['accuracy'];
        }
        if ($coordLine) {
            $this->text($img, $smallSz, $pad, $y, $white, $this->fontRegular, $coordLine);
        }
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
