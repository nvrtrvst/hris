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
        Schema::create('presensi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pegawai_id')->constrained('pegawai')->cascadeOnDelete();
            $table->foreignId('jadwal_id')->nullable()->constrained('jadwal')->nullOnDelete();
            $table->foreignId('unit_sekolah_id')->constrained('unit_sekolah')->cascadeOnDelete();

            $table->date('tanggal');
            $table->time('jam_masuk')->nullable();
            $table->time('jam_keluar')->nullable();

            $table->enum('status', ['hadir', 'telat', 'izin', 'sakit', 'alpa'])->default('alpa');

            $table->decimal('latitude_masuk', 10, 8)->nullable();
            $table->decimal('longitude_masuk', 11, 8)->nullable();
            $table->string('foto_masuk')->nullable();
            $table->integer('jarak_masuk_meter')->nullable();

            $table->decimal('latitude_keluar', 10, 8)->nullable();
            $table->decimal('longitude_keluar', 11, 8)->nullable();
            $table->string('foto_keluar')->nullable();
            $table->integer('jarak_keluar_meter')->nullable();

            $table->text('keterangan')->nullable();

            $table->timestamps();

            // Unique index to prevent double clock-in for same schedule/day
            $table->unique(['pegawai_id', 'tanggal', 'jadwal_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('presensi');
    }
};
