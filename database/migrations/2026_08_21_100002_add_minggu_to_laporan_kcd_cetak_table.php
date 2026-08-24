<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('laporan_kcd_cetak', function (Blueprint $table) {
            $table->unsignedInteger('minggu')->nullable()->after('periode_key');
        });
    }

    public function down(): void
    {
        Schema::table('laporan_kcd_cetak', function (Blueprint $table) {
            $table->dropColumn('minggu');
        });
    }
};
