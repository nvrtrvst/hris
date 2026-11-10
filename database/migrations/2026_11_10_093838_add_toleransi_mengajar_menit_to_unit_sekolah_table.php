<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_sekolah', function (Blueprint $table) {
            $table->unsignedInteger('toleransi_mengajar_menit')->nullable()->after('toleransi_menit');
        });

        // Backfill dari toleransi_menit agar unit lama tidak berubah perilaku.
        DB::table('unit_sekolah')->update([
            'toleransi_mengajar_menit' => DB::raw('toleransi_menit'),
        ]);
    }

    public function down(): void
    {
        Schema::table('unit_sekolah', function (Blueprint $table) {
            $table->dropColumn('toleransi_mengajar_menit');
        });
    }
};
