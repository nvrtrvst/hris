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
            $table->integer('toleransi_menit')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('unit_sekolah', function (Blueprint $table) {
            $table->dropColumn('toleransi_menit');
        });
    }
};
