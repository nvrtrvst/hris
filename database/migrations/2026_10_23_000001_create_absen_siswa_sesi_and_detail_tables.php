<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Absen siswa oleh guru piket — satu sesi per (unit, tanggal, kelas)
     * berisi detail kehadiran per siswa. Sesi opsional punya satu laporan
     * piket pemiliknya (satu piket bisa absen banyak kelas).
     */
    public function up(): void
    {
        Schema::create('absen_siswa_sesi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_sekolah_id')->nullable()->constrained('unit_sekolah')->nullOnDelete();
            $table->foreignId('pegawai_id')->constrained('pegawai')->cascadeOnDelete();
            $table->foreignId('laporan_piket_id')->nullable()->constrained('laporan_piket')->nullOnDelete();
            $table->date('tanggal');
            $table->string('class_id', 50);
            $table->string('class_label', 100);
            $table->timestamp('done_at')->nullable();
            $table->timestamps();

            $table->unique(['unit_sekolah_id', 'tanggal', 'class_id'], 'absen_siswa_sesi_unit_tanggal_kelas_unique');
            $table->index(['pegawai_id', 'tanggal']);
            $table->index(['tanggal']);
        });

        Schema::create('absen_siswa_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('absen_siswa_sesi_id')->constrained('absen_siswa_sesi')->cascadeOnDelete();
            $table->string('nis', 30);
            $table->string('nama_snapshot', 150);
            $table->enum('status', ['hadir', 'telat', 'izin', 'sakit', 'alpa'])->default('hadir');
            $table->string('keterangan', 255)->nullable();
            $table->timestamps();

            $table->unique(['absen_siswa_sesi_id', 'nis'], 'absen_siswa_detail_sesi_nis_unique');
            $table->index(['nis', 'status']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absen_siswa_detail');
        Schema::dropIfExists('absen_siswa_sesi');
    }
};
