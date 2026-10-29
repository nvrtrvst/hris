<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('piket_siswa_terlambat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_sekolah_id')->constrained('unit_sekolah')->cascadeOnDelete();
            $table->foreignId('piket_sesi_id')->constrained('piket_sesi')->cascadeOnDelete();
            $table->foreignId('piket_pos_id')->nullable()->constrained('piket_pos')->nullOnDelete();
            $table->foreignId('pegawai_id')->nullable()->constrained('pegawai')->nullOnDelete();
            $table->date('tanggal');
            $table->string('class_id', 50);
            $table->string('class_label', 100);
            $table->string('nis', 30);
            $table->string('nama_snapshot', 150);
            $table->string('jam_terlambat', 8);
            $table->string('keterangan', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['unit_sekolah_id', 'tanggal', 'nis'], 'piket_siswa_terlambat_unit_tanggal_nis_uq');
            $table->index(['piket_sesi_id', 'tanggal']);
            $table->index(['class_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('piket_siswa_terlambat');
    }
};
