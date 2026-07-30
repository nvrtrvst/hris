<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hari_libur', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->string('nama', 100);
            $table->foreignId('unit_sekolah_id')->nullable()->constrained('unit_sekolah')->cascadeOnDelete();
            $table->string('tipe', 20)->default('nasional');
            $table->text('keterangan')->nullable();
            $table->timestamps();
            $table->unique(['tanggal', 'unit_sekolah_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hari_libur');
    }
};
