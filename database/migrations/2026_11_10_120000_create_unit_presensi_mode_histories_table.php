<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_presensi_mode_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_sekolah_id')->constrained('unit_sekolah')->cascadeOnDelete();
            $table->string('mode', 20);
            $table->date('berlaku_mulai');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['unit_sekolah_id', 'berlaku_mulai'], 'unit_presensi_mode_unit_tanggal_uq');
            $table->index(['unit_sekolah_id', 'berlaku_mulai'], 'unit_presensi_mode_unit_tanggal_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_presensi_mode_histories');
    }
};
