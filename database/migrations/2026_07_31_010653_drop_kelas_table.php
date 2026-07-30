<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('kelas');
    }

    public function down(): void
    {
        Schema::create('kelas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_sekolah_id')->constrained('unit_sekolah')->onDelete('cascade');
            $table->foreignId('jurusan_id')->nullable()->constrained('jurusan')->onDelete('set null');
            $table->string('kode_external', 100)->nullable();
            $table->string('nama');
            $table->integer('tingkat');
            $table->timestamps();
            $table->unique(['kode_external', 'unit_sekolah_id']);
        });
    }
};
