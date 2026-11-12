<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pekan_ujian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_sekolah_id')->constrained('unit_sekolah')->cascadeOnDelete();
            $table->string('nama', 191);
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->string('keterangan', 500)->nullable();
            $table->timestamps();

            $table->index(['unit_sekolah_id', 'tanggal_mulai', 'tanggal_selesai'], 'pekan_ujian_unit_tanggal_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pekan_ujian');
    }
};
