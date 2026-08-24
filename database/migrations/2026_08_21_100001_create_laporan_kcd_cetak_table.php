<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laporan_kcd_cetak', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('unit_sekolah_id')->nullable()->constrained('unit_sekolah')->nullOnDelete();
            $table->string('periode_key'); // YYYY-MM
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('nomor_cetak');
            $table->timestamps();

            $table->index(['unit_sekolah_id', 'periode_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laporan_kcd_cetak');
    }
};
