<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcement_pegawai', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            // Nama tabel pegawai tidak jamak (bukan pegawais) — wajib eksplisit.
            $table->foreignId('pegawai_id')->constrained('pegawai')->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['announcement_id', 'pegawai_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_pegawai');
    }
};
