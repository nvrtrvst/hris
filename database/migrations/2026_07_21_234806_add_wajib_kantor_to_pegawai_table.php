<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pegawai', function (Blueprint $table) {
            $table->boolean('wajib_kantor')->default(false)->after('status_kepegawaian');
        });
    }

    public function down(): void
    {
        Schema::table('pegawai', function (Blueprint $table) {
            $table->dropColumn('wajib_kantor');
        });
    }
};
