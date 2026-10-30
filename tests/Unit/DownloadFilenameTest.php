<?php

namespace Tests\Unit;

use App\Helpers\DownloadFilename;
use App\Models\UnitSekolah;
use Tests\TestCase;

class DownloadFilenameTest extends TestCase
{
    public function test_person_name_is_sanitized_and_included(): void
    {
        $this->assertSame(
            'Presensi_Pegawai_Ahmad_A_Hidayat_2026-10.pdf',
            DownloadFilename::person('Presensi_Pegawai', 'Ahmad A. Hidayat', '2026-10', 'pdf'),
        );
    }

    public function test_collection_uses_unit_and_period(): void
    {
        $unit = new UnitSekolah(['singkatan' => 'SMK']);

        $this->assertSame(
            'Laporan_Presensi_SMK_2026-10-01_to_2026-10-31.xlsx',
            DownloadFilename::collection('Laporan_Presensi', $unit, '2026-10-01_to_2026-10-31', 'xlsx'),
        );
    }

    public function test_missing_unit_uses_semua_unit(): void
    {
        $this->assertSame(
            'Laporan_Pengawasan_Semua_Unit_2026-10.pdf',
            DownloadFilename::collection('Laporan_Pengawasan', null, '2026-10', 'pdf'),
        );
    }
}
