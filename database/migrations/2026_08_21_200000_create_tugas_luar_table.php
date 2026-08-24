<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jadwal "Tugas Luar" (dinas di luar unit) — dibuat admin/atasan di muka
     * (terjadwal) agar pegawai bisa absen lewat geofence. Mendadak = pegawai
     * ajukan via mobile (pending) tanpa jadwal ini.
     */
    public function up(): void
    {
        Schema::create('tugas_luar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pegawai_id')->constrained('pegawai')->cascadeOnDelete();
            $table->foreignId('unit_sekolah_id')->nullable()->constrained('unit_sekolah')->nullOnDelete();
            $table->date('tanggal');
            $table->time('jam_mulai')->nullable();
            $table->time('jam_selesai')->nullable();
            $table->string('tujuan');
            $table->text('keterangan')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['pegawai_id', 'tanggal']);
            $table->index(['tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tugas_luar');
    }
};
