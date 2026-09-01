<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mata_pelajaran', function (Blueprint $table) {
            $table->foreignId('unit_sekolah_id')->nullable()->after('nama')->constrained('unit_sekolah');
        });
    }

    public function down(): void
    {
        Schema::table('mata_pelajaran', function (Blueprint $table) {
            $table->dropForeign(['unit_sekolah_id']);
            $table->dropColumn('unit_sekolah_id');
        });
    }
};
