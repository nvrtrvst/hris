<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Akar masalah deadlock 1213 saat 250 absen serentak:
     * SELECT ... FOR UPDATE pada baris yang belum ada mengambil GAP LOCK di index unik
     * (pegawai_id, tanggal, jadwal_id) — dan karena jadwal_id NULL untuk lembur/kantor,
     * index unik lama TIDAK melindungi kasus tersebut (NULL diizinkan duplikat di MySQL).
     *
     * Solusi: generated column `presensi_key` + unique index (pegawai_id, presensi_key)
     * sehingga MySQL sendiri yang menolak double-absen — tanpa gap lock, deadlock mustahil.
     *
     * Nilai:
     * - lembur  -> 'L{tanggal}'
     * - kantor  -> 'K{tanggal}'
     * - mengajar-> 'M{tanggal}-{jadwal_id}'
     * - lainnya -> NULL (record izin/manual tanpa jadwal tidak dibatasi, banyak NULL diizinkan)
     */
    public function up(): void
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

        Schema::table('presensi', function (Blueprint $table) use ($expression) {
            // VIRTUAL (bukan STORED): kombinasi STORED + CONCAT gagal di MySQL 8
            // dengan error 1215 (quirks MySQL). VIRTUAL tidak menyimpan nilai tapi
            // tetap bisa dipakai di unique index, dan lebih hemat storage.
            // JANGAN pakai ->nullable() — MySQL menolak NULL/NOT NULL eksplisit
            // pada deklarasi generated column (error 1064); ekspresi yang bisa
            // menghasilkan NULL sudah otomatis nullable.
            $table->string('presensi_key', 60)->after('tipe_presensi')
                ->virtualAs($expression);
        });

        Schema::table('presensi', function (Blueprint $table) {
            $table->unique(['pegawai_id', 'presensi_key'], 'presensi_pegawai_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('presensi', function (Blueprint $table) {
            $table->dropUnique('presensi_pegawai_key_unique');
        });

        Schema::table('presensi', function (Blueprint $table) {
            $table->dropColumn('presensi_key');
        });
    }
};
