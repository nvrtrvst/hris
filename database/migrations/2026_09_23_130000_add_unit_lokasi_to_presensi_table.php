<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presensi', function (Blueprint $table) {
            $table->foreignId('unit_lokasi_masuk_id')->nullable()->after('unit_sekolah_id')->constrained('unit_lokasi')->nullOnDelete();
            $table->foreignId('unit_lokasi_keluar_id')->nullable()->after('unit_lokasi_masuk_id')->constrained('unit_lokasi')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('presensi', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unit_lokasi_masuk_id');
            $table->dropConstrainedForeignId('unit_lokasi_keluar_id');
        });
    }
};
