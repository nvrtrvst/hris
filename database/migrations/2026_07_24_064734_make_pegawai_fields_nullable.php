<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pegawai', function (Blueprint $table) {
            $table->string('nama_lengkap', 255)->nullable()->change();
            $table->string('tempat_lahir', 255)->nullable()->change();
            $table->date('tanggal_lahir')->nullable()->change();
            $table->string('jenis_kelamin', 10)->nullable()->change();
            $table->string('agama', 255)->nullable()->change();
            $table->string('status_pernikahan', 255)->nullable()->change();
            $table->text('alamat_ktp')->nullable()->change();
            $table->string('no_hp', 20)->nullable()->change();
            $table->string('status_kepegawaian', 50)->nullable()->change();
            $table->date('tanggal_mulai_kerja')->nullable()->change();
            $table->string('pendidikan_terakhir', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('pegawai', function (Blueprint $table) {
            $table->string('nama_lengkap', 255)->nullable(false)->change();
            $table->string('tempat_lahir', 255)->nullable(false)->change();
            $table->date('tanggal_lahir')->nullable(false)->change();
            $table->string('jenis_kelamin', 10)->nullable(false)->change();
            $table->string('agama', 255)->nullable(false)->change();
            $table->string('status_pernikahan', 255)->nullable(false)->change();
            $table->text('alamat_ktp')->nullable(false)->change();
            $table->string('no_hp', 20)->nullable(false)->change();
            $table->string('status_kepegawaian', 50)->nullable(false)->change();
            $table->date('tanggal_mulai_kerja')->nullable(false)->change();
            $table->string('pendidikan_terakhir', 255)->nullable(false)->change();
        });
    }
};
