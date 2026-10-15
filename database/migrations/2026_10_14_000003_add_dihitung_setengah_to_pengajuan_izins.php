<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Flag setengah hari: izin yang disertai tugas (dikonfirmasi admin saat
     * approve) dihitung 0,5 hari untuk rate kehadiran payroll.
     */
    public function up(): void
    {
        Schema::table('pengajuan_izins', function (Blueprint $table) {
            $table->boolean('dihitung_setengah')->default(false)->after('dihitung_hadir_kcd');
        });
    }

    public function down(): void
    {
        Schema::table('pengajuan_izins', function (Blueprint $table) {
            $table->dropColumn('dihitung_setengah');
        });
    }
};
