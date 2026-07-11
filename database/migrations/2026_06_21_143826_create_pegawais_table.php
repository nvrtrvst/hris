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
        Schema::create('pegawai', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('nik', 16)->unique();
            $table->string('nama_lengkap');
            $table->string('foto')->nullable();
            $table->string('tempat_lahir');
            $table->date('tanggal_lahir');
            $table->enum('jenis_kelamin', ['L', 'P']);
            $table->string('agama');
            $table->string('status_pernikahan');
            $table->integer('jumlah_tanggungan')->default(0);
            $table->text('alamat_ktp');
            $table->text('alamat_domisili')->nullable();
            $table->string('no_hp');
            $table->string('no_hp_darurat')->nullable();
            $table->string('email')->nullable();

            $table->enum('status_kepegawaian', ['tetap', 'kontrak', 'honorer', 'gtt']);
            $table->date('tanggal_mulai_kerja');
            $table->date('tanggal_akhir_kontrak')->nullable();
            $table->foreignId('atasan_langsung_id')->nullable()->constrained('pegawai')->onDelete('set null');
            $table->enum('status_aktif', ['aktif', 'cuti', 'nonaktif', 'resign'])->default('aktif');
            $table->text('alasan_nonaktif')->nullable();

            $table->string('pendidikan_terakhir');
            $table->string('nuptk')->nullable();

            $table->text('no_rekening')->nullable(); // encrypted
            $table->text('nama_bank')->nullable(); // encrypted
            $table->text('npwp')->nullable(); // encrypted
            $table->text('no_bpjs_kesehatan')->nullable(); // encrypted
            $table->text('no_bpjs_ketenagakerjaan')->nullable(); // encrypted

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pegawai');
    }
};
