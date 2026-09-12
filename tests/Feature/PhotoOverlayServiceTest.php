<?php

namespace Tests\Feature;

use App\Services\PhotoOverlayService;
use Illuminate\Support\Facades\Http;
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

        $this->assertStringStartsWith('RIFF', $out); // magic webp
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

        $this->assertStringStartsWith('RIFF', $out);
    }

    public function test_renders_with_map_coordinates(): void
    {
        if (! function_exists('imagewebp')) {
            $this->markTestSkipped('GD webp support required');
        }

        // lat/lng triggers renderMap() — verifies tile fetch + composite path
        // tidak fatal meski tile fetch gagal (offline test env)
        Http::fake([
            '*basemaps.cartocdn.com*' => Http::response('', 500),
            '*tile.openstreetmap.org*' => Http::response('', 500),
        ]);

        $data = [
            'label' => 'BUKTI PRESENSI',
            'pegawai' => 'Ahmad',
            'unit' => 'SMK',
            'time' => '07:00:00 WIB',
            'coordinates' => '-7.313299, 107.793070',
            'latitude' => -7.313299,
            'longitude' => 107.793070,
        ];

        $out = app(PhotoOverlayService::class)->applyToImage($this->sampleBinary(), $data);

        $this->assertStringStartsWith('RIFF', $out);
    }

    public function test_renders_with_radius_badge_dalam(): void
    {
        if (! function_exists('imagewebp')) {
            $this->markTestSkipped('GD webp support required');
        }

        Http::fake([
            '*basemaps.cartocdn.com*' => Http::response('', 500),
            '*tile.openstreetmap.org*' => Http::response('', 500),
        ]);

        $data = [
            'label' => 'BUKTI PRESENSI',
            'pegawai' => 'Ahmad',
            'unit' => 'SMK',
            'time' => '07:00:00 WIB',
            'coordinates' => '-7.313299, 107.793070',
            'latitude' => -7.313299,
            'longitude' => 107.793070,
            'radius_status' => true,
        ];

        $out = app(PhotoOverlayService::class)->applyToImage($this->sampleBinary(), $data);

        $this->assertStringStartsWith('RIFF', $out);
    }

    public function test_renders_radius_badge_skipped_for_tugas_luar(): void
    {
        if (! function_exists('imagewebp')) {
            $this->markTestSkipped('GD webp support required');
        }

        Http::fake([
            '*basemaps.cartocdn.com*' => Http::response('', 500),
            '*tile.openstreetmap.org*' => Http::response('', 500),
        ]);

        $data = [
            'label' => 'TUGAS LUAR',
            'is_tugas_luar' => true,
            'pegawai' => 'Ahmad',
            'unit' => 'SMK',
            'time' => '07:00:00 WIB',
            'coordinates' => '-7.313299, 107.793070',
            'latitude' => -7.313299,
            'longitude' => 107.793070,
        ];

        $out = app(PhotoOverlayService::class)->applyToImage($this->sampleBinary(), $data);

        $this->assertStringStartsWith('RIFF', $out);
    }
}
