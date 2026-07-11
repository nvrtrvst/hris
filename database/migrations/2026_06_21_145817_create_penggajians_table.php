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
        Schema::create('penggajian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pegawai_id')->constrained('pegawai')->cascadeOnDelete();
            $table->string('periode_bulan', 7); // e.g. 06-2026
            $table->date('tanggal_generate');
            $table->decimal('total_pendapatan', 15, 2)->default(0);
            $table->decimal('total_potongan', 15, 2)->default(0);
            $table->decimal('gaji_bersih', 15, 2)->default(0);
            $table->enum('status', ['draft', 'finalized', 'paid'])->default('draft');
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->unique(['pegawai_id', 'periode_bulan']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('penggajian');
    }
};
