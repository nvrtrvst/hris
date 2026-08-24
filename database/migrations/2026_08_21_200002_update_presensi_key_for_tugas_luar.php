<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah cabang 'Tugas Luar' ke generated column `presensi_key`:
     * - tugas luar -> 'TL{tanggal}'
     *
     * Harus konsisten dengan MobileController::buildPresensiKey() (PHP).
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $expression = "CASE
                WHEN is_lembur = 1 THEN 'L' || tanggal
                WHEN is_tugas_luar = 1 THEN 'TL' || tanggal
                WHEN tipe_presensi = 'kantor' THEN 'K' || tanggal
                WHEN jadwal_id IS NOT NULL THEN 'M' || tanggal || '-' || jadwal_id
                ELSE NULL
            END";
        } else {
            $expression = "CASE
                WHEN is_lembur = 1 THEN CONCAT('L', tanggal)
                WHEN is_tugas_luar = 1 THEN CONCAT('TL', tanggal)
                WHEN tipe_presensi = 'kantor' THEN CONCAT('K', tanggal)
                WHEN jadwal_id IS NOT NULL THEN CONCAT('M', tanggal, '-', jadwal_id)
                ELSE NULL
            END";
        }

        Schema::table('presensi', function (Blueprint $table) {
            $table->dropUnique('presensi_pegawai_key_unique');
        });

        Schema::table('presensi', function (Blueprint $table) {
            $table->dropColumn('presensi_key');
        });

        Schema::table('presensi', function (Blueprint $table) use ($expression) {
            $table->string('presensi_key', 60)->after('tipe_presensi')
                ->virtualAs($expression);
        });

        Schema::table('presensi', function (Blueprint $table) {
            $table->unique(['pegawai_id', 'presensi_key'], 'presensi_pegawai_key_unique');
        });
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $expression = "CASE
                WHEN is_lembur = 1 THEN 'L' || tanggal
                WHEN tipe_presensi = 'kantor' THEN 'K' || tanggal
                WHEN jadwal_id IS NOT NULL THEN 'M' || tanggal || '-' || jadwal_id
                ELSE NULL
            END";
        } else {
            $expression = "CASE
                WHEN is_lembur = 1 THEN CONCAT('L', tanggal)
                WHEN tipe_presensi = 'kantor' THEN CONCAT('K', tanggal)
                WHEN jadwal_id IS NOT NULL THEN CONCAT('M', tanggal, '-', jadwal_id)
                ELSE NULL
            END";
        }

        Schema::table('presensi', function (Blueprint $table) {
            $table->dropUnique('presensi_pegawai_key_unique');
        });

        Schema::table('presensi', function (Blueprint $table) {
            $table->dropColumn('presensi_key');
        });

        Schema::table('presensi', function (Blueprint $table) use ($expression) {
            $table->string('presensi_key', 60)->after('tipe_presensi')
                ->virtualAs($expression);
        });

        Schema::table('presensi', function (Blueprint $table) {
            $table->unique(['pegawai_id', 'presensi_key'], 'presensi_pegawai_key_unique');
        });
    }
};
