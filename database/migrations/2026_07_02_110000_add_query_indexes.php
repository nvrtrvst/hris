<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presensi', function (Blueprint $table) {
            $table->index('tanggal', 'presensi_tanggal_index');
            $table->index(['unit_sekolah_id', 'tanggal'], 'presensi_unit_tanggal_index');
        });

        Schema::table('penggajian', function (Blueprint $table) {
            $table->index('periode_bulan', 'penggajian_periode_index');
        });
    }

    public function down(): void
    {
        Schema::table('presensi', function (Blueprint $table) {
            $table->dropIndex('presensi_tanggal_index');
            $table->dropIndex('presensi_unit_tanggal_index');
        });

        Schema::table('penggajian', function (Blueprint $table) {
            $table->dropIndex('penggajian_periode_index');
        });
    }
};
