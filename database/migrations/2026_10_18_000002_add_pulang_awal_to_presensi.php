<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presensi', function (Blueprint $table) {
            $table->boolean('is_pulang_awal')->default(false);
            $table->unsignedInteger('menit_pulang_awal')->nullable();
            $table->boolean('pulang_awal_diizinkan')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('presensi', function (Blueprint $table) {
            $table->dropColumn(['is_pulang_awal', 'menit_pulang_awal', 'pulang_awal_diizinkan']);
        });
    }
};
