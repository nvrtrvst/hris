<?php

namespace Tests\Unit;

use App\Models\Presensi;
use PHPUnit\Framework\TestCase;

class PresensiStatusTest extends TestCase
{
    public function test_status_no_tolerance(): void
    {
        $this->assertSame('hadir', Presensi::statusAt('07:30:00', '07:30:00'));
        $this->assertSame('telat', Presensi::statusAt('07:30:01', '07:30:00'));
    }

    public function test_status_with_tolerance(): void
    {
        $this->assertSame('hadir', Presensi::statusAt('07:40:00', '07:30:00', 10));
        $this->assertSame('hadir', Presensi::statusAt('07:30:00', '07:30:00', 15));
        $this->assertSame('telat', Presensi::statusAt('07:50:01', '07:30:00', 10));
    }
}
