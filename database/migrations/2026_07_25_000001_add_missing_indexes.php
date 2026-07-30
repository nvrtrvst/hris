<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presensi', function (Blueprint $table) {
            if (! Schema::hasIndex('presensi', 'presensi_status_index')) {
                $table->index('status', 'presensi_status_index');
            }
            if (! Schema::hasIndex('presensi', 'presensi_is_lembur_index')) {
                $table->index('is_lembur', 'presensi_is_lembur_index');
            }
            if (! Schema::hasIndex('presensi', 'presensi_lembur_status_index')) {
                $table->index('lembur_status', 'presensi_lembur_status_index');
            }
        });

        Schema::table('pengajuan_izins', function (Blueprint $table) {
            if (! Schema::hasIndex('pengajuan_izins', 'pengajuan_izins_status_index')) {
                $table->index('status', 'pengajuan_izins_status_index');
            }
        });

        Schema::table('jadwal', function (Blueprint $table) {
            if (! Schema::hasIndex('jadwal', 'jadwal_jenis_jadwal_index')) {
                $table->index('jenis_jadwal', 'jadwal_jenis_jadwal_index');
            }
        });

        Schema::table('pegawai_unit', function (Blueprint $table) {
            if (! Schema::hasIndex('pegawai_unit', 'pegawai_unit_pegawai_unit_index')) {
                $table->index(['pegawai_id', 'unit_sekolah_id'], 'pegawai_unit_pegawai_unit_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('presensi', function (Blueprint $table) {
            $table->dropIndex('presensi_status_index');
            $table->dropIndex('presensi_is_lembur_index');
            $table->dropIndex('presensi_lembur_status_index');
        });

        Schema::table('pengajuan_izins', function (Blueprint $table) {
            $table->dropIndex('pengajuan_izins_status_index');
        });

        Schema::table('jadwal', function (Blueprint $table) {
            $table->dropIndex('jadwal_jenis_jadwal_index');
        });

        Schema::table('pegawai_unit', function (Blueprint $table) {
            $table->dropIndex('pegawai_unit_pegawai_unit_index');
        });
    }
};
