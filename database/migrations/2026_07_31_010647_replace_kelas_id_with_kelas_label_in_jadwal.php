<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('jadwal', function (Blueprint $table) {
            $table->dropForeign(['kelas_id']);
            $table->string('kelas_label', 255)->nullable()->after('kelas_id');
        });

        DB::table('jadwal')
            ->whereNotNull('kelas_id')
            ->orderBy('id')
            ->each(function ($jadwal) {
                $kelas = DB::table('kelas')->where('id', $jadwal->kelas_id)->first();
                if ($kelas) {
                    DB::table('jadwal')->where('id', $jadwal->id)->update([
                        'kelas_label' => $kelas->tingkat.' - '.$kelas->nama,
                    ]);
                }
            });

        Schema::table('jadwal', function (Blueprint $table) {
            $table->dropColumn('kelas_id');
        });
    }

    public function down(): void
    {
        Schema::table('jadwal', function (Blueprint $table) {
            $table->foreignId('kelas_id')->nullable()->after('kelas_label');
            $table->dropColumn('kelas_label');
        });
    }
};
