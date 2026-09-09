<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Migrasi dua perubahan terkait refactor status & presensi default:
     *
     *   1. Ubah default `wajib_kantor` ke TRUE. Pegawai baru otomatis
     *      berstatus wajib masuk kantor; admin bisa uncheck di Edit Page
     *      bila ada kasus khusus (tetap harus di unit + ada jadwal).
     *
     *   2. Rename nilai `status_kepegawaian` lama ke format GTYS baru.
     *      Sumber nilai lama: PegawaiConstants lama
     *      (`tetap`, `kontrak`, `honorer`, `gtt`). Pemetaan disusun dengan
     *      asumsi: `tetap`/`kontrak`/`honorer` dominan dipakai untuk guru;
     *      admin bisa koreksi manual setelah migrasi via Edit Page jika
     *      salah klasifikasi. Nilai kosong / unknown di-default-kan ke
     *      `pegawai_pemula` agar tidak NULL dan lolos validasi `in:`.
     *
     * Idempotent: pakai WHERE di setiap UPDATE agar bisa diulang tanpa
     * efek ganda.
     */
    public function up(): void
    {
        // (1) Default wajib_kantor -> TRUE.
        Schema::table('pegawai', function (Blueprint $table) {
            $table->boolean('wajib_kantor')->default(true)->change();
        });

        // (2) Remap status_kepegawaian ke format GTYS.
        $mapping = [
            'tetap' => 'guru_tetap_yayasan',
            'kontrak' => 'guru_tidak_tetap',
            'honorer' => 'guru_pemula',
            'gtt' => 'guru_tidak_tetap',
        ];

        foreach ($mapping as $old => $new) {
            DB::table('pegawai')
                ->where('status_kepegawaian', $old)
                ->update(['status_kepegawaian' => $new]);
        }

        // Sisa nilai yang bukan dari mapping lama (atau NULL) → pegawai_pemula.
        // Idempotent: kalau sudah di nilai yang valid, query ini no-op.
        DB::statement("
            UPDATE pegawai
            SET status_kepegawaian = 'pegawai_pemula'
            WHERE status_kepegawaian IS NULL
               OR status_kepegawaian NOT IN (
                   'guru_tetap_yayasan',
                   'guru_tidak_tetap',
                   'guru_pemula',
                   'ptt',
                   'pegawai_tidak_tetap',
                   'pegawai_pemula'
               )
        ");
    }

    public function down(): void
    {
        // Best-effort rollback: hanya kembalikan default wajib_kantor.
        // Pemetaan status tidak di-reverse karena mapping bersifat best-guess.
        Schema::table('pegawai', function (Blueprint $table) {
            $table->boolean('wajib_kantor')->default(false)->change();
        });
    }
};
