<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Populate existing komponen_gaji dengan kode defaults berdasarkan nama pattern
        // Ini backward compatibility agar stripos pattern tetap bekerja
        DB::table('komponen_gaji')->whereNull('kode')->update([
            'kode' => DB::raw("
                CASE
                    WHEN LOWER(nama) LIKE '%gaji pokok%' OR LOWER(nama) LIKE '%basic salary%' THEN 'gaji_pokok'
                    WHEN LOWER(nama) LIKE '%tunjangan kehadiran%' OR LOWER(nama) LIKE '%tunjangan hadir%' THEN 'tunjangan_kehadiran'
                    WHEN LOWER(nama) LIKE '%tunjangan makan%' OR LOWER(nama) LIKE '%makan%' THEN 'tunjangan_makan'
                    WHEN LOWER(nama) LIKE '%tunjangan transport%' OR LOWER(nama) LIKE '%transport%' THEN 'tunjangan_transport'
                    WHEN LOWER(nama) LIKE '%potongan telat%' THEN 'potongan_telat'
                    WHEN LOWER(nama) LIKE '%potongan alpa%' THEN 'potongan_alpa'
                    WHEN LOWER(nama) LIKE '%tunjangan masa bakti%' OR LOWER(nama) LIKE '%masa kerja%' THEN 'tunjangan_masa_bakti'
                    WHEN LOWER(nama) LIKE '%lembur%' THEN 'tunjangan_lembur'
                    ELSE NULL
                END
            "),
        ]);
    }

    public function down(): void
    {
        // Kolom kode dibuat oleh migration 2026_07_02_100000.
    }
};
