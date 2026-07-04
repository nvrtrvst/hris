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
        Schema::create('skala_masa_baktis', function (Blueprint $table) {
            $table->id();
            $table->integer('masa_kerja_tahun')->unique()->comment('Tahun masa bakti (0, 1, 2, dst)');
            $table->decimal('nominal_gaji', 15, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('skala_masa_baktis');
    }
};
