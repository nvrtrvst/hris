<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presensi', function (Blueprint $table) {
            $table->string('foto_masuk_status', 20)->nullable()->after('foto_masuk');
            $table->text('foto_masuk_error')->nullable()->after('foto_masuk_status');
            $table->string('foto_keluar_status', 20)->nullable()->after('foto_keluar');
            $table->text('foto_keluar_error')->nullable()->after('foto_keluar_status');
        });
    }

    public function down(): void
    {
        Schema::table('presensi', function (Blueprint $table) {
            $table->dropColumn(['foto_masuk_status', 'foto_masuk_error', 'foto_keluar_status', 'foto_keluar_error']);
        });
    }
};
