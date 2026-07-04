<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Ubah ENUM menjadi VARCHAR agar lebih fleksibel dan tidak error saat nambah opsi baru
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE komponen_gaji MODIFY jenis VARCHAR(50) NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE komponen_gaji MODIFY jenis ENUM('fixed','persentase','dinamis_kehadiran','dinamis_jam_mengajar') NOT NULL");
    }
};
