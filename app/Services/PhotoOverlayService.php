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

    public function applyToImage(string $imageContent, array $data): string
    {
        $img = @imagecreatefromstring($imageContent);
        if ($img === false) {
            throw new \InvalidArgumentException('Gagal memproses gambar untuk overlay.');
        }

        try {
            $this->renderOverlay($img, $data);
            ob_start();
            imagejpeg($img, null, 85);

            return ob_get_clean();
        } finally {
            imagedestroy($img);
        }
    }

    private function renderOverlay($img, array $data): void
    {
        $width = imagesx($img);
        $height = imagesy($img);

        $panelH = (int) round($height * 0.35);
        $panelTop = $height - $panelH;
        $pad = max(14, (int) round($width * 0.035));

        $panel = imagecolorallocatealpha($img, 0, 0, 0, 80);
        $white = imagecolorallocate($img, 255, 255, 255);
        $gray = imagecolorallocate($img, 200, 212, 220);
        $emerald = imagecolorallocate($img, 110, 231, 183);
        $amber = imagecolorallocate($img, 252, 211, 77);

        imagefilledrectangle($img, 0, $panelTop, $width, $height, $panel);

        $isLembur = ! empty($data['is_lembur']);
        $labelColor = $isLembur ? $amber : $emerald;

        $labelSz = max(14, (int) round($width * 0.032));
        $timeSz = max(22, (int) round($width * 0.052));
        $bodySz = max(11, (int) round($width * 0.025));
        $smallSz = max(9, (int) round($width * 0.020));

        $y = $panelTop + $pad;

        $this->text($img, $labelSz, $pad, $y, $labelColor, $this->fontBold, $data['label'] ?? 'BUKTI PRESENSI');
        $y += $labelSz + 6;

        if (! empty($data['time'])) {
            $this->text($img, $timeSz, $pad, $y, $white, $this->fontBold, $data['time']);
            $y += $timeSz + 5;
        }

        if (! empty($data['pegawai'])) {
            $this->text($img, $bodySz, $pad, $y, $white, $this->fontBold, $data['pegawai']);
            $y += $bodySz + 4;
        }

        if (! empty($data['unit'])) {
            $this->text($img, $bodySz, $pad, $y, $gray, $this->fontRegular, $data['unit']);
            $y += $bodySz + 4;
        }

        if (! empty($data['date'])) {
            $this->text($img, $smallSz, $pad, $y, $gray, $this->fontRegular, $data['date']);
            $y += $smallSz + 4;
        }

        $coordLine = '';
        if (! empty($data['coordinates'])) {
            $coordLine .= $data['coordinates'];
        }
        if (! empty($data['distance'])) {
            $coordLine .= ' | Jarak: '.$data['distance'];
        }
        if ($coordLine) {
            $this->text($img, $smallSz, $pad, $y, $white, $this->fontRegular, $coordLine);
            $y += $smallSz + 3;
        }

        if (! empty($data['accuracy'])) {
            $this->text($img, $smallSz, $pad, $y, $gray, $this->fontRegular, $data['accuracy']);
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
