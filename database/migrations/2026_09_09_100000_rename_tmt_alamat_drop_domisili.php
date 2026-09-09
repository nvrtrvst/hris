<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambah kolom baru
        Schema::table('pegawai', function (Blueprint $table) {
            $table->date('tmt_mengajar')->nullable()->after('status_aktif');
            $table->text('alamat')->nullable()->after('jumlah_tanggungan');
        });

        // 2. Copy data dari kolom lama
        DB::table('pegawai')->whereNotNull('tanggal_mulai_kerja')->update(['tmt_mengajar' => DB::raw('tanggal_mulai_kerja')]);
        DB::table('pegawai')->whereNotNull('alamat_ktp')->update(['alamat' => DB::raw('alamat_ktp')]);

        // 3. Drop kolom lama
        Schema::table('pegawai', function (Blueprint $table) {
            $table->dropColumn(['tanggal_mulai_kerja', 'alamat_ktp', 'alamat_domisili']);
        });
    }

    public function down(): void
    {
        Schema::table('pegawai', function (Blueprint $table) {
            $table->date('tanggal_mulai_kerja')->nullable()->after('status_aktif');
            $table->text('alamat_ktp')->nullable()->after('jumlah_tanggungan');
            $table->text('alamat_domisili')->nullable()->after('alamat_ktp');
        });

        DB::table('pegawai')->whereNotNull('tmt_mengajar')->update(['tanggal_mulai_kerja' => DB::raw('tmt_mengajar')]);
        DB::table('pegawai')->whereNotNull('alamat')->update(['alamat_ktp' => DB::raw('alamat')]);

        Schema::table('pegawai', function (Blueprint $table) {
            $table->dropColumn(['tmt_mengajar', 'alamat']);
        });
    }
};
