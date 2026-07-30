<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_sekolah', function (Blueprint $table) {
            $table->integer('max_jam_minggu')->default(30)->after('durasi_jp');
        });
    }

    public function down(): void
    {
        Schema::table('unit_sekolah', function (Blueprint $table) {
            $table->dropColumn('max_jam_minggu');
        });
    }
};
