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
        Schema::table('unit_sekolah', function (Blueprint $table) {
            $table->time('jam_kerja_sabtu_mulai')->nullable()->after('jam_pulang_kantor');
            $table->time('jam_kerja_sabtu_selesai')->nullable()->after('jam_kerja_sabtu_mulai');
        });
    }

    public function down(): void
    {
        Schema::table('unit_sekolah', function (Blueprint $table) {
            $table->dropColumn(['jam_kerja_sabtu_mulai', 'jam_kerja_sabtu_selesai']);
        });
    }
};
