<?php

namespace Tests\Unit;

use App\Models\Presensi;
use PHPUnit\Framework\TestCase;

class PresensiStatusTest extends TestCase
{
    public function test_status_is_late_only_after_required_start_time(): void
    {
        $this->assertSame('hadir', Presensi::statusAt('07:30:00', '07:30:00'));
        $this->assertSame('telat', Presensi::statusAt('07:30:01', '07:30:00'));
    }
}
