<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dokumen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_sekolah_id')->nullable()->constrained('unit_sekolah')->nullOnDelete();
            $table->string('judul');
            $table->string('kategori', 50)->index();
            $table->string('nomor', 100)->nullable();
            $table->string('penerbit', 191)->nullable();
            $table->date('tanggal_dokumen')->nullable();
            $table->date('tanggal_kedaluwarsa')->nullable()->index();
            $table->text('keterangan')->nullable();
            $table->string('file_path');
            $table->string('file_name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dokumen');
    }
};
