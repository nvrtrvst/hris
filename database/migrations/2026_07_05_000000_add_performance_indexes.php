<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * [PERF] Index untuk kolom yang sering difilter/diurutkan.
     */
    public function up(): void
    {
        Schema::table('pegawai', function (Blueprint $table) {
            if (! Schema::hasIndex('pegawai', 'idx_pegawai_status_aktif')) {
                $table->index('status_aktif', 'idx_pegawai_status_aktif');
            }
        });

        Schema::table('penggajian', function (Blueprint $table) {
            if (! Schema::hasIndex('penggajian', 'idx_penggajian_periode_bulan')) {
                $table->index('periode_bulan', 'idx_penggajian_periode_bulan');
            }
        });

        Schema::table('pengajuan_izins', function (Blueprint $table) {
            if (! Schema::hasIndex('pengajuan_izins', 'idx_pengajuan_izins_pegawai_id')) {
                $table->index('pegawai_id', 'idx_pengajuan_izins_pegawai_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pegawai', function (Blueprint $table) {
            $table->dropIndex('idx_pegawai_status_aktif');
        });

        Schema::table('penggajian', function (Blueprint $table) {
            $table->dropIndex('idx_penggajian_periode_bulan');
        });

        Schema::table('pengajuan_izins', function (Blueprint $table) {
            $table->dropIndex('idx_pengajuan_izins_pegawai_id');
        });
    }
};
