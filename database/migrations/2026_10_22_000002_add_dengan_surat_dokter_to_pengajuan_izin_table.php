<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengajuan_izins', function (Blueprint $table) {
            $table->boolean('dengan_surat_dokter')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('pengajuan_izins', function (Blueprint $table) {
            $table->dropColumn('dengan_surat_dokter');
        });
    }
};
