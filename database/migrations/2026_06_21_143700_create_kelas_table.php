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
        Schema::create('kelas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_sekolah_id')->constrained('unit_sekolah')->onDelete('cascade');
            $table->foreignId('jurusan_id')->nullable()->constrained('jurusan')->onDelete('set null');
            $table->string('nama');
            $table->integer('tingkat');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kelas');
    }
};
