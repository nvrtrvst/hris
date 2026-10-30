<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_sekolah', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_jeda_sesi_menit')
                ->default(120)
                ->after('toleransi_slide_menit')
                ->comment('Jeda maksimum antar JP dalam satu sesi mengajar, termasuk istirahat.');
        });
    }

    public function down(): void
    {
        Schema::table('unit_sekolah', function (Blueprint $table) {
            $table->dropColumn('max_jeda_sesi_menit');
        });
    }
};
