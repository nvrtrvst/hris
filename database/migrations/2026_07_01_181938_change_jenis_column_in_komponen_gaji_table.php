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
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // Ubah ENUM menjadi VARCHAR agar lebih fleksibel dan tidak error saat nambah opsi baru
        DB::statement('ALTER TABLE komponen_gaji MODIFY jenis VARCHAR(50) NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE komponen_gaji MODIFY jenis ENUM('fixed','persentase','dinamis_kehadiran','dinamis_jam_mengajar') NOT NULL");
    }
};
