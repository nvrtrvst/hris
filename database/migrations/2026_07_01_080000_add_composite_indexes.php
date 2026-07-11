<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * [FIX] Menambahkan composite index untuk optimasi query conflict detection
     * dan filter jadwal per pegawai + hari.
     */
    public function up(): void
    {
        Schema::table('jadwal', function (Blueprint $table) {
            $table->index(['pegawai_id', 'hari', 'jam_mulai', 'jam_selesai'], 'idx_jadwal_pegawai_hari_jam');
        });

        Schema::table('presensi', function (Blueprint $table) {
            $table->index(['pegawai_id', 'tanggal', 'status'], 'idx_presensi_pegawai_tanggal_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jadwal', function (Blueprint $table) {
            $table->dropIndex('idx_jadwal_pegawai_hari_jam');
        });

        Schema::table('presensi', function (Blueprint $table) {
            $table->dropIndex('idx_presensi_pegawai_tanggal_status');
        });
    }
};
