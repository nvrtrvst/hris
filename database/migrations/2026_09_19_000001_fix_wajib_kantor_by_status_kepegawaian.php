<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // GTYS (Guru Tetap) & PTY (Pegawai Tetap) → wajib_kantor = TRUE
        DB::table('pegawai')
            ->where('status_aktif', 'aktif')
            ->whereIn('status_kepegawaian', ['guru_tetap_yayasan', 'pegawai_tetap_yayasan'])
            ->where('wajib_kantor', 0)
            ->update(['wajib_kantor' => 1]);

        // GTT (Guru Tidak Tetap) & Guru Pemula → wajib_kantor = FALSE
        DB::table('pegawai')
            ->where('status_aktif', 'aktif')
            ->whereIn('status_kepegawaian', ['guru_tidak_tetap', 'guru_pemula'])
            ->where('wajib_kantor', 1)
            ->update(['wajib_kantor' => 0]);

        // Hapus record anomali Diki Firmansyah: tipe=kantor, jadwal_id=NULL,
        // tidak mungkin dari flow normal (mobile maupun admin).
        DB::table('presensi')->where('id', 1111)->delete();
    }

    public function down(): void
    {
        // Reversal tidak diperlukan — model hook akan auto-sync setelah migration ini.
    }
};
