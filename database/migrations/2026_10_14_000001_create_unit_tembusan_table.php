<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jabatan penerima tembusan izin per unit (mis. Wakasek Kurikulum).
     * Notifikasi + push CC otomatis saat pengajuan diajukan dan diputus.
     */
    public function up(): void
    {
        Schema::create('unit_tembusan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_sekolah_id')->constrained('unit_sekolah')->cascadeOnDelete();
            $table->foreignId('jabatan_id')->constrained('jabatan')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['unit_sekolah_id', 'jabatan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_tembusan');
    }
};
