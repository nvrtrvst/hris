<?php

namespace Tests\Feature;

use App\Services\PhotoOverlayService;
use Tests\TestCase;

class PhotoOverlayServiceTest extends TestCase
{
    private function sampleBinary(): string
    {
        $img = imagecreatetruecolor(40, 40);
        $red = imagecolorallocate($img, 200, 50, 50);
        imagefill($img, 0, 0, $red);
        ob_start();
        imagepng($img);
        $binary = ob_get_clean();
        imagedestroy($img);

        return $binary;
    }

    public function test_renders_with_alamat(): void
    {
        if (! function_exists('imagewebp')) {
            $this->markTestSkipped('GD webp support required');
        }

        $data = [
            'label' => 'BUKTI PRESENSI',
            'pegawai' => 'Ahmad',
            'unit' => 'SMK',
            'time' => '23:54:14 WIB',
            'date' => 'Senin, 25 Agustus 2026',
            'coordinates' => '-7.313299, 107.793070',
            'accuracy' => '20m',
            'kecamatan' => 'Cisurupan',
            'kelurahan' => null,
        ];

        $out = app(PhotoOverlayService::class)->applyToImage($this->sampleBinary(), $data);

        $this->assertStringStartsWith("RIFF", $out); // magic webp
    }

    public function test_renders_without_alamat(): void
    {
        if (! function_exists('imagewebp')) {
            $this->markTestSkipped('GD webp support required');
        }

        $data = [
            'label' => 'BUKTI LEMBUR',
            'pegawai' => 'Ahmad',
            'time' => '23:54:14',
            'coordinates' => '-7.3, 107.7',
        ];

        $out = app(PhotoOverlayService::class)->applyToImage($this->sampleBinary(), $data);

        $this->assertStringStartsWith("RIFF", $out);
    }
}
