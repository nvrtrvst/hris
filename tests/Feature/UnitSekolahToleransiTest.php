<?php

namespace Tests\Feature;

use App\Models\UnitSekolah;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitSekolahToleransiTest extends TestCase
{
    use RefreshDatabase;

    public function test_toleransi_mengajar_fallback_ke_toleransi_kantor(): void
    {
        $unit = $this->makeUnit([
            'toleransi_menit' => 4,
            'toleransi_mengajar_menit' => null,
        ]);

        $this->assertSame(4, $unit->toleransiMengajarMenit());
    }

    public function test_toleransi_mengajar_eksplisit_tidak_memakai_toleransi_kantor(): void
    {
        $unit = $this->makeUnit([
            'toleransi_menit' => 4,
            'toleransi_mengajar_menit' => 9,
        ]);

        $this->assertSame(9, $unit->toleransiMengajarMenit());
    }

    private function makeUnit(array $attributes): UnitSekolah
    {
        return UnitSekolah::create(array_merge([
            'nama' => 'SMP Toleransi',
            'singkatan' => 'SMPT',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
            'jam_masuk_kantor' => '07:00',
        ], $attributes));
    }
}
