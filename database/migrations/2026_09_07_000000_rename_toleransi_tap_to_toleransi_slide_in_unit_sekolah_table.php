<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_sekolah', function (Blueprint $table) {
            $table->renameColumn('toleransi_tap_menit', 'toleransi_slide_menit');
        });
    }

    public function down(): void
    {
        Schema::table('unit_sekolah', function (Blueprint $table) {
            $table->renameColumn('toleransi_slide_menit', 'toleransi_tap_menit');
        });
    }
};
