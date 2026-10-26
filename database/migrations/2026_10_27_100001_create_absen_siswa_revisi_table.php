<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absen_siswa_revisi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('absen_siswa_sesi_id')->constrained('absen_siswa_sesi')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('peran', 20)->default('petugas');
            $table->json('ringkasan')->nullable();
            $table->json('details')->nullable();
            $table->text('alasan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absen_siswa_revisi');
    }
};
