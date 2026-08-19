<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengajuan_izins', function (Blueprint $table) {
            $table->text('catatan_approval')->nullable()->after('alasan_penolakan');
        });
    }

    public function down(): void
    {
        Schema::table('pengajuan_izins', function (Blueprint $table) {
            $table->dropColumn('catatan_approval');
        });
    }
};
