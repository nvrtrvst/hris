<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Laporan Piket Harian — checklist tugas guru piket per hari.
     * Satu baris per (pegawai, tanggal). Item checklist (dengan timestamp
     * server + geofence per centang) disimpan sebagai JSON karena kecil
     * (<=10 entry/hari) dan tidak diquery satuan.
     */
    public function up(): void
    {
        Schema::create('laporan_piket', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pegawai_id')->constrained('pegawai')->cascadeOnDelete();
            $table->foreignId('jadwal_id')->nullable()->constrained('jadwal')->nullOnDelete();
            $table->foreignId('unit_sekolah_id')->nullable()->constrained('unit_sekolah')->nullOnDelete();
            $table->date('tanggal');
            $table->json('items')->nullable();
            $table->enum('status', ['draft', 'submitted'])->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['pegawai_id', 'tanggal']);
            $table->index(['tanggal']);
            $table->index(['unit_sekolah_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laporan_piket');
    }
};
