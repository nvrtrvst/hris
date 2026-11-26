<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presensi', function (Blueprint $table) {
            if (! Schema::hasIndex('presensi', 'presensi_lembur_status_tanggal_idx')) {
                $table->index(['is_lembur', 'lembur_status', 'tanggal'], 'presensi_lembur_status_tanggal_idx');
            }
            if (Schema::hasIndex('presensi', 'presensi_tanggal_index')) {
                $table->dropIndex('presensi_tanggal_index');
            }
            if (Schema::hasIndex('presensi', 'presensi_is_lembur_index')) {
                $table->dropIndex('presensi_is_lembur_index');
            }
            if (Schema::hasIndex('presensi', 'presensi_lembur_status_index')) {
                $table->dropIndex('presensi_lembur_status_index');
            }
        });

        Schema::table('pengajuan_izins', function (Blueprint $table) {
            if (! Schema::hasIndex('pengajuan_izins', 'pengajuan_izins_pegawai_status_tanggal_idx')) {
                $table->index(
                    ['pegawai_id', 'status', 'tanggal_mulai', 'tanggal_selesai'],
                    'pengajuan_izins_pegawai_status_tanggal_idx'
                );
            }
            if (Schema::hasIndex('pengajuan_izins', 'idx_pengajuan_izins_pegawai_id')) {
                $table->dropIndex('idx_pengajuan_izins_pegawai_id');
            }
        });

        Schema::table('pegawai_unit', function (Blueprint $table) {
            if (! Schema::hasIndex('pegawai_unit', 'pegawai_unit_unit_pegawai_idx')) {
                $table->index(['unit_sekolah_id', 'pegawai_id'], 'pegawai_unit_unit_pegawai_idx');
            }
        });

        Schema::table('penggajian', function (Blueprint $table) {
            if (! Schema::hasIndex('penggajian', 'penggajian_periode_status_idx')) {
                $table->index(['periode_bulan', 'status'], 'penggajian_periode_status_idx');
            }
        });

        Schema::table('unit_presensi_mode_histories', function (Blueprint $table) {
            if (Schema::hasIndex('unit_presensi_mode_histories', 'unit_presensi_mode_unit_tanggal_idx')) {
                $table->dropIndex('unit_presensi_mode_unit_tanggal_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('unit_presensi_mode_histories', function (Blueprint $table) {
            if (! Schema::hasIndex('unit_presensi_mode_histories', 'unit_presensi_mode_unit_tanggal_idx')) {
                $table->index(['unit_sekolah_id', 'berlaku_mulai'], 'unit_presensi_mode_unit_tanggal_idx');
            }
        });

        Schema::table('penggajian', function (Blueprint $table) {
            if (Schema::hasIndex('penggajian', 'penggajian_periode_status_idx')) {
                $table->dropIndex('penggajian_periode_status_idx');
            }
        });

        Schema::table('pegawai_unit', function (Blueprint $table) {
            if (Schema::hasIndex('pegawai_unit', 'pegawai_unit_unit_pegawai_idx')) {
                $table->dropIndex('pegawai_unit_unit_pegawai_idx');
            }
        });

        Schema::table('pengajuan_izins', function (Blueprint $table) {
            if (! Schema::hasIndex('pengajuan_izins', 'idx_pengajuan_izins_pegawai_id')) {
                $table->index('pegawai_id', 'idx_pengajuan_izins_pegawai_id');
            }
            if (Schema::hasIndex('pengajuan_izins', 'pengajuan_izins_pegawai_status_tanggal_idx')) {
                $table->dropIndex('pengajuan_izins_pegawai_status_tanggal_idx');
            }
        });

        Schema::table('presensi', function (Blueprint $table) {
            if (! Schema::hasIndex('presensi', 'presensi_lembur_status_index')) {
                $table->index('lembur_status', 'presensi_lembur_status_index');
            }
            if (! Schema::hasIndex('presensi', 'presensi_is_lembur_index')) {
                $table->index('is_lembur', 'presensi_is_lembur_index');
            }
            if (! Schema::hasIndex('presensi', 'presensi_tanggal_index')) {
                $table->index('tanggal', 'presensi_tanggal_index');
            }
            if (Schema::hasIndex('presensi', 'presensi_lembur_status_tanggal_idx')) {
                $table->dropIndex('presensi_lembur_status_tanggal_idx');
            }
        });
    }
};
