<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_sekolah', function (Blueprint $table) {
            $table->decimal('bobot_sakit_surat', 3, 2)->nullable();
            $table->decimal('bobot_sakit_tanpa_surat', 3, 2)->nullable();
            $table->decimal('bobot_izin', 3, 2)->nullable();
            $table->decimal('bobot_izin_setengah', 3, 2)->nullable();
            $table->decimal('bobot_cuti', 3, 2)->nullable();
            $table->decimal('bobot_alpa', 3, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('unit_sekolah', function (Blueprint $table) {
            $table->dropColumn([
                'bobot_sakit_surat',
                'bobot_sakit_tanpa_surat',
                'bobot_izin',
                'bobot_izin_setengah',
                'bobot_cuti',
                'bobot_alpa',
            ]);
        });
    }
};
