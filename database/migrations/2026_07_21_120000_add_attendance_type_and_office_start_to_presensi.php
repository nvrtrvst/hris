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
            $table->time('jam_masuk_kantor')->default('07:30:00')->after('radius_meter');
        });

        Schema::table('presensi', function (Blueprint $table) {
            $table->string('tipe_presensi', 20)->default('mengajar')->after('unit_sekolah_id');
            $table->index(['pegawai_id', 'tanggal', 'tipe_presensi'], 'presensi_pegawai_tanggal_tipe_index');
        });

        Schema::table('jadwal', function (Blueprint $table) {
            $table->index(['pegawai_id', 'hari'], 'jadwal_pegawai_hari_index');
        });

        DB::table('presensi')->where('is_lembur', true)->update(['tipe_presensi' => 'lembur']);
    }

    public function down(): void
    {
        Schema::table('jadwal', function (Blueprint $table) {
            $table->dropIndex('jadwal_pegawai_hari_index');
        });

        Schema::table('presensi', function (Blueprint $table) {
            $table->dropIndex('presensi_pegawai_tanggal_tipe_index');
            $table->dropColumn('tipe_presensi');
        });

        Schema::table('unit_sekolah', function (Blueprint $table) {
            $table->dropColumn('jam_masuk_kantor');
        });
    }
};
