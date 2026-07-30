<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_presensi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presensi_id')->constrained('presensi')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('aksi', 50);
            $table->string('field', 50)->nullable();
            $table->string('nilai_lama', 100)->nullable();
            $table->string('nilai_baru', 100)->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_presensi');
    }
};
