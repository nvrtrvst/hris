<?php

namespace Tests\Feature;

use App\Models\UnitSekolah;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Perilaku FileHelper::fotoUrl untuk logo unit:
 *  - file ADA di image_disk (public) → URL publik /storage/... (bukan route presensi.photo)
 *  - file TIDAK ada di public (mis. foto presensi di disk presensi) → route presensi.photo
 *
 * Konteks bug: logo disimpan di disk 'public' tapi fotoUrl mengarahkannya ke
 * route presensi.photo yang membaca disk 'presensi' (private) → 404/gambar rusak.
 */
class FileHelperUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_logo_url_served_from_public_disk_when_file_exists(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('unit_logos/logo.png', 'fake-image');

        $unit = UnitSekolah::create([
            'nama' => 'SD Uji',
            'singkatan' => 'SD',
            'logo' => 'unit_logos/logo.png',
        ]);

        $this->assertNotNull($unit->logo_url);
        $this->assertStringContainsString('/storage/unit_logos/logo.png', $unit->logo_url);
        $this->assertStringNotContainsString('presensi/photo', $unit->logo_url);
    }

    public function test_logo_url_falls_back_to_protected_route_when_not_on_public_disk(): void
    {
        Storage::fake('public');

        $unit = UnitSekolah::create([
            'nama' => 'SD Uji',
            'singkatan' => 'SD',
            'logo' => 'unit_logos/missing.png',
        ]);

        // File tidak ada di disk public → tetap lewat route presensi.photo
        // (perilaku lama untuk foto presensi yang disimpan di disk 'presensi').
        $this->assertStringContainsString('presensi/photo', $unit->logo_url);
    }
}
