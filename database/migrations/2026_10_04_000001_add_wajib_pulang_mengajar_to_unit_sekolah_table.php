<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_sekolah', function (Blueprint $table) {
            $table->boolean('wajib_pulang_mengajar')->default(false);
            $table->unsignedSmallInteger('pulang_sebelum_menit')->default(20);
        });
    }

    public function down(): void
    {
        Schema::table('unit_sekolah', function (Blueprint $table) {
            $table->dropColumn(['wajib_pulang_mengajar', 'pulang_sebelum_menit']);
        });
    }
};
