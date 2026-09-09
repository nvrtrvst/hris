<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pegawai', function (Blueprint $table) {
            $table->smallInteger('pendidikan_tahun_lulus')->unsigned()->nullable()->after('pendidikan_jurusan');
            $table->string('pendidikan_asal_sekolah', 255)->nullable()->after('pendidikan_tahun_lulus');
            $table->string('sk_nomor', 100)->nullable()->after('pendidikan_asal_sekolah');
            $table->date('sk_tanggal')->nullable()->after('sk_nomor');
        });
    }

    public function down(): void
    {
        Schema::table('pegawai', function (Blueprint $table) {
            $table->dropColumn([
                'pendidikan_tahun_lulus',
                'pendidikan_asal_sekolah',
                'sk_nomor',
                'sk_tanggal',
            ]);
        });
    }
};
