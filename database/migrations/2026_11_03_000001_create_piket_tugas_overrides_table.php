<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('piket_tugas_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pegawai_id')->constrained('pegawai')->cascadeOnDelete();
            $table->foreignId('unit_sekolah_id')->nullable()->constrained('unit_sekolah')->nullOnDelete();
            $table->date('tanggal');
            $table->boolean('ada_amanat')->default(false);
            $table->boolean('tersampaikan')->default(false);
            $table->text('catatan')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['pegawai_id', 'tanggal']);
            $table->index(['unit_sekolah_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('piket_tugas_overrides');
    }
};
