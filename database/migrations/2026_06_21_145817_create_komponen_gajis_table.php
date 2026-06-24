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
        Schema::create('komponen_gaji', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->enum('tipe', ['pendapatan', 'potongan']);
            $table->enum('jenis', ['fixed', 'persentase', 'dinamis_kehadiran']);
            $table->decimal('nilai_default', 15, 2)->nullable();
            $table->boolean('is_taxable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('pegawai_komponen_gaji', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pegawai_id')->constrained('pegawai')->cascadeOnDelete();
            $table->foreignId('komponen_gaji_id')->constrained('komponen_gaji')->cascadeOnDelete();
            $table->decimal('nominal', 15, 2)->nullable(); // Specific amount for this employee
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pegawai_komponen_gaji');
        Schema::dropIfExists('komponen_gaji');
    }
};
