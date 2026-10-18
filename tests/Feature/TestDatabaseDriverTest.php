<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Penjaga agar suite CI benar-benar jalan di MySQL (bukan sqlite bawaan phpunit.xml).
 * Lokal: skip kecuali CI_REQUIRE_MYSQL diset.
 */
class TestDatabaseDriverTest extends TestCase
{
    public function test_driver_sesuai_environment(): void
    {
        if (! filter_var(env('CI_REQUIRE_MYSQL', false), FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('CI_REQUIRE_MYSQL tidak diset.');
        }

        $this->assertSame('mysql', DB::connection()->getDriverName());
    }
}
