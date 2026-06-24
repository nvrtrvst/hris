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
        Schema::create('penggajian_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penggajian_id')->constrained('penggajian')->cascadeOnDelete();
            $table->foreignId('komponen_gaji_id')->nullable()->constrained('komponen_gaji')->nullOnDelete();
            $table->string('nama_komponen');
            $table->enum('tipe', ['pendapatan', 'potongan']);
            $table->decimal('nominal', 15, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('penggajian_detail');
    }
};
