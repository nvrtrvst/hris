<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_sekolah', function (Blueprint $table) {
            $table->boolean('sakit_wajib_tugas')->default(false)->after('bobot_alpa');
        });

        Schema::table('pengajuan_izins', function (Blueprint $table) {
            $table->boolean('tugas_terverifikasi')->default(false)->after('dengan_surat_dokter');
            $table->timestamp('tugas_diverifikasi_at')->nullable()->after('tugas_terverifikasi');
            $table->foreignId('tugas_diverifikasi_oleh')->nullable()->after('tugas_diverifikasi_at')
                ->constrained('users')->nullOnDelete();
        });

        // Data lama yang sudah bersurat dianggap lengkap agar payroll historis
        // tidak turun ke 50% saat aturan baru diaktifkan.
        DB::table('pengajuan_izins')
            ->where('status', 'disetujui')
            ->where('jenis_izin', 'sakit')
            ->where('dengan_surat_dokter', true)
            ->update([
                'tugas_terverifikasi' => true,
                'tugas_diverifikasi_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('pengajuan_izins', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tugas_diverifikasi_oleh');
            $table->dropColumn(['tugas_terverifikasi', 'tugas_diverifikasi_at']);
        });

        Schema::table('unit_sekolah', function (Blueprint $table) {
            $table->dropColumn('sakit_wajib_tugas');
        });
    }
};
