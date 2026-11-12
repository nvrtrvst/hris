<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jadwal', function (Blueprint $table) {
            $table->foreignId('pekan_ujian_id')
                ->nullable()
                ->after('unit_lokasi_id')
                ->constrained('pekan_ujian')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('jadwal', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pekan_ujian_id');
        });
    }
};
