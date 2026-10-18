<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jam kerja Minggu per unit: unit yang memang beroperasi Minggu (mis. LPQ)
     * diisi agar presensi pegawai di hari Minggu tetap dibuka.
     */
    public function up(): void
    {
        Schema::table('unit_sekolah', function (Blueprint $table) {
            $table->time('jam_kerja_minggu_mulai')->nullable()->after('jam_kerja_sabtu_selesai');
            $table->time('jam_kerja_minggu_selesai')->nullable()->after('jam_kerja_minggu_mulai');
        });
    }

    public function down(): void
    {
        Schema::table('unit_sekolah', function (Blueprint $table) {
            $table->dropColumn(['jam_kerja_minggu_mulai', 'jam_kerja_minggu_selesai']);
        });
    }
};
