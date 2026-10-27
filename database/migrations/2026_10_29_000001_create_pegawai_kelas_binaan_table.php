<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pegawai_kelas_binaan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pegawai_id')->constrained('pegawai')->cascadeOnDelete();
            $table->foreignId('unit_sekolah_id')->constrained('unit_sekolah')->cascadeOnDelete();
            $table->string('class_id', 50)->nullable();
            $table->string('class_label', 100);
            $table->string('tahun_ajaran', 20);
            $table->string('semester', 10);
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->unique(
                ['unit_sekolah_id', 'class_label', 'tahun_ajaran', 'semester'],
                'pkb_unit_kelas_periode_uq'
            );
            $table->index(['pegawai_id', 'aktif'], 'pkb_pegawai_aktif_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pegawai_kelas_binaan');
    }
};
