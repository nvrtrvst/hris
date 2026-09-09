<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Backup old nuptk → nuptk_lama
        Schema::table('pegawai', function (Blueprint $table) {
            $table->string('nuptk_lama', 50)->nullable()->after('nik');
        });
        DB::table('pegawai')->whereNotNull('nuptk')->update(['nuptk_lama' => DB::raw('nuptk')]);

        // 2. Drop old nuptk (data backed up in nuptk_lama)
        Schema::table('pegawai', function (Blueprint $table) {
            $table->dropColumn('nuptk');
        });

        // 3. Rename nip → nuptk
        Schema::table('pegawai', function (Blueprint $table) {
            $table->renameColumn('nip', 'nuptk');
        });
    }

    public function down(): void
    {
        Schema::table('pegawai', function (Blueprint $table) {
            $table->string('nip', 50)->nullable()->unique()->after('nik');
        });
        DB::table('pegawai')->whereNotNull('nuptk')->update(['nip' => DB::raw('nuptk')]);
        Schema::table('pegawai', function (Blueprint $table) {
            $table->dropColumn('nuptk');
        });
    }
};
