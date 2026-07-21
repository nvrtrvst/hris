<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_sekolah', function (Blueprint $table) {
            $table->time('jam_pulang_kantor')->nullable()->default('15:00')->after('jam_masuk_kantor');
        });
    }

    public function down(): void
    {
        Schema::table('unit_sekolah', function (Blueprint $table) {
            $table->dropColumn('jam_pulang_kantor');
        });
    }
};
