<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nominal tunjangan jabatan per komponen gaji (mis. Tunjangan Wakasek
     * untuk jabatan Wakasek Kurikulum). Pegawai rangkap jabatan menerima
     * penjumlahan semua mapping yang cocok (full sebulan).
     */
    public function up(): void
    {
        Schema::create('komponen_jabatan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('komponen_gaji_id')->constrained('komponen_gaji')->cascadeOnDelete();
            $table->foreignId('jabatan_id')->constrained('jabatan')->cascadeOnDelete();
            $table->decimal('nominal', 15, 2);
            $table->timestamps();

            $table->unique(['komponen_gaji_id', 'jabatan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('komponen_jabatan');
    }
};
