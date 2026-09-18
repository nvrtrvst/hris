<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('unit_lokasi')) {
            Schema::create('unit_lokasi', function (Blueprint $table) {
                $table->id();
                $table->foreignId('unit_sekolah_id')->constrained('unit_sekolah')->cascadeOnDelete();
                $table->string('nama');
                $table->decimal('latitude', 10, 8);
                $table->decimal('longitude', 11, 8);
                $table->integer('radius_meter')->default(50);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pegawai_unit_lokasi')) {
            Schema::create('pegawai_unit_lokasi', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pegawai_id')->constrained('pegawai')->cascadeOnDelete();
                $table->foreignId('unit_lokasi_id')->constrained('unit_lokasi')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['pegawai_id', 'unit_lokasi_id']);
            });
        }

        if (! Schema::hasColumn('jadwal', 'unit_lokasi_id')) {
            Schema::table('jadwal', function (Blueprint $table) {
                $table->foreignId('unit_lokasi_id')->nullable()->after('unit_sekolah_id')->constrained('unit_lokasi')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('jadwal', 'unit_lokasi_id')) {
            Schema::table('jadwal', function (Blueprint $table) {
                $table->dropForeign(['unit_lokasi_id']);
                $table->dropColumn('unit_lokasi_id');
            });
        }
        Schema::dropIfExists('pegawai_unit_lokasi');
        Schema::dropIfExists('unit_lokasi');
    }
};
