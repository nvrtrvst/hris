<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit index (2026-08-15):
     * - jadwal_pegawai_hari_index (pegawai_id, hari) = prefix persis dari
     *   idx_jadwal_pegawai_hari_jam (pegawai_id, hari, jam_mulai, jam_selesai) -> redundan.
     * - penggajian_periode_index (periode_bulan) = duplikat dari
     *   idx_penggajian_periode_bulan (periode_bulan) yang dibuat belakangan.
     */
    public function up(): void
    {
        if (Schema::hasIndex('jadwal', 'jadwal_pegawai_hari_index')) {
            Schema::table('jadwal', function (Blueprint $table) {
                $table->dropIndex('jadwal_pegawai_hari_index');
            });
        }

        if (Schema::hasIndex('penggajian', 'penggajian_periode_index')) {
            Schema::table('penggajian', function (Blueprint $table) {
                $table->dropIndex('penggajian_periode_index');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasIndex('jadwal', 'jadwal_pegawai_hari_index')) {
            Schema::table('jadwal', function (Blueprint $table) {
                $table->index(['pegawai_id', 'hari'], 'jadwal_pegawai_hari_index');
            });
        }

        if (! Schema::hasIndex('penggajian', 'penggajian_periode_index')) {
            Schema::table('penggajian', function (Blueprint $table) {
                $table->index('periode_bulan', 'penggajian_periode_index');
            });
        }
    }
};
