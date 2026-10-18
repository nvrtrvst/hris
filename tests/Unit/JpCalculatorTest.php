<?php

namespace Tests\Unit;

use App\Models\Jadwal;
use App\Models\UnitSekolah;
use App\Services\JpCalculator;
use PHPUnit\Framework\TestCase;

class JpCalculatorTest extends TestCase
{
    private function jadwal(string $mulai, string $selesai, ?int $durasiJp = null): Jadwal
    {
        $jadwal = new Jadwal;
        $jadwal->jam_mulai = $mulai;
        $jadwal->jam_selesai = $selesai;
        $unit = null;
        if ($durasiJp !== null) {
            $unit = new UnitSekolah;
            $unit->durasi_jp = $durasiJp;
        }
        $jadwal->setRelation('unitSekolah', $unit);

        return $jadwal;
    }

    public function test_bobot_jp_sesuai_durasi_unit(): void
    {
        $this->assertSame(1.0, JpCalculator::weightFromJadwal($this->jadwal('07:00', '07:45', 45)));
        $this->assertSame(2.0, JpCalculator::weightFromJadwal($this->jadwal('07:00', '08:30', 45)));
        $this->assertSame(1.5, JpCalculator::weightFromJadwal($this->jadwal('07:00', '07:30', 20)));
    }

    public function test_durasi_jp_default_45_bila_unit_tak_ada(): void
    {
        $this->assertSame(1.0, JpCalculator::weightFromJadwal($this->jadwal('07:00', '07:45')));
    }

    public function test_durasi_jp_nol_tidak_bagi_nol(): void
    {
        $this->assertSame(1.0, JpCalculator::weightFromJadwal($this->jadwal('07:00', '07:45', 0)));
    }

    public function test_target_minggu_adalah_24(): void
    {
        $this->assertSame(24, JpCalculator::TARGET_JP_MINGGU);
    }
}
