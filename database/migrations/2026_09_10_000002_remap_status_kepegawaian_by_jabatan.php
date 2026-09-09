<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Remap status_kepegawaian by jabatan (query-based, idempotent).
     *
     * Konteks: migrasi sebelumnya me-remap nilai lama (tetap/kontrak/honorer/
     * gtt) ke format GTYS tanpa membedakan guru vs staf — staf (TU, bendahara,
     * dll) ikut ter-stempel 'guru_*'. Migrasi ini membereskannya dengan
     * subquery EXISTS/NOT EXISTS pada pivot pegawai_unit + jabatan.is_guru:
     *
     *   - STAF (tidak punya jabatan is_guru):
     *       guru_tetap_yayasan / tetap          → pegawai_tetap_yayasan
     *       guru_tidak_tetap / kontrak / gtt     → pegawai_tidak_tetap
     *       guru_pemula / honorer / ptt / NULL   → pegawai_pemula
     *   - GURU (punya jabatan is_guru):
     *       pegawai_tetap_yayasan / tetap        → guru_tetap_yayasan
     *       pegawai_tidak_tetap / kontrak / gtt  → guru_tidak_tetap
     *       pegawai_pemula / honorer / ptt / NULL → guru_pemula
     *
     * Pegawai yang sudah ber-status valid untuk grupnya tidak tersentuh
     * (WHERE ... NOT IN) sehingga migrasi aman dijalankan berulang.
     */
    public function up(): void
    {
        $guruExists = '
            EXISTS (
                SELECT 1 FROM pegawai_unit pu
                JOIN jabatan j ON j.id = pu.jabatan_id
                WHERE pu.pegawai_id = pegawai.id AND j.is_guru = 1
            )';

        // ── STAF: nilai guru_* / legacy → pegawai_* ──
        DB::statement("
            UPDATE pegawai
            SET status_kepegawaian = 'pegawai_tetap_yayasan'
            WHERE NOT {$guruExists}
              AND status_kepegawaian IN ('guru_tetap_yayasan', 'tetap')
        ");

        DB::statement("
            UPDATE pegawai
            SET status_kepegawaian = 'pegawai_tidak_tetap'
            WHERE NOT {$guruExists}
              AND status_kepegawaian IN ('guru_tidak_tetap', 'kontrak', 'gtt', 'ptt')
        ");

        DB::statement("
            UPDATE pegawai
            SET status_kepegawaian = 'pegawai_pemula'
            WHERE NOT {$guruExists}
              AND (
                    status_kepegawaian IS NULL
                    OR status_kepegawaian IN ('guru_pemula', 'honorer')
                    OR status_kepegawaian NOT IN (
                        'guru_tetap_yayasan', 'pegawai_tetap_yayasan',
                        'guru_tidak_tetap', 'pegawai_tidak_tetap',
                        'guru_pemula', 'pegawai_pemula'
                    )
              )
        ");

        // ── GURU: nilai pegawai_* / legacy → guru_* ──
        DB::statement("
            UPDATE pegawai
            SET status_kepegawaian = 'guru_tetap_yayasan'
            WHERE {$guruExists}
              AND status_kepegawaian IN ('pegawai_tetap_yayasan', 'tetap')
        ");

        DB::statement("
            UPDATE pegawai
            SET status_kepegawaian = 'guru_tidak_tetap'
            WHERE {$guruExists}
              AND status_kepegawaian IN ('pegawai_tidak_tetap', 'kontrak', 'gtt', 'ptt')
        ");

        DB::statement("
            UPDATE pegawai
            SET status_kepegawaian = 'guru_pemula'
            WHERE {$guruExists}
              AND (
                    status_kepegawaian IS NULL
                    OR status_kepegawaian IN ('pegawai_pemula', 'honorer')
                    OR status_kepegawaian NOT IN (
                        'guru_tetap_yayasan', 'pegawai_tetap_yayasan',
                        'guru_tidak_tetap', 'pegawai_tidak_tetap',
                        'guru_pemula', 'pegawai_pemula'
                    )
              )
        ");
    }

    public function down(): void
    {
        // Tidak di-reverse: mapping best-guess berdasar jabatan, rollback
        // akan menimpa koreksi manual admin pasca-migrasi.
    }
};
