<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $duplicateMap = [
        11 => 2,  // TK Yayasan -> TK
        12 => 3,  // SD Yayasan -> SD
        13 => 4,  // SMP Yayasan -> SMP
        14 => 9,  // SMA Yayasan -> SMA
        15 => 5,  // SMK Yayasan -> SMK
    ];

    public function up(): void
    {
        // 1. Reassign FK references from duplicate units to canonical ones
        foreach ($this->duplicateMap as $dupId => $canonId) {
            DB::table('pegawai_unit')
                ->where('unit_sekolah_id', $dupId)
                ->update(['unit_sekolah_id' => $canonId]);

            DB::table('jadwal')
                ->where('unit_sekolah_id', $dupId)
                ->update(['unit_sekolah_id' => $canonId]);

            DB::table('users')
                ->where('unit_sekolah_id', $dupId)
                ->update(['unit_sekolah_id' => $canonId]);

            DB::table('kelas')
                ->where('unit_sekolah_id', $dupId)
                ->update(['unit_sekolah_id' => $canonId]);

            DB::table('komponen_gaji')
                ->where('unit_sekolah_id', $dupId)
                ->update(['unit_sekolah_id' => $canonId]);

            DB::table('presensi')
                ->where('unit_sekolah_id', $dupId)
                ->update(['unit_sekolah_id' => $canonId]);
        }

        // 2. Delete duplicate unit records
        DB::table('unit_sekolah')->whereIn('id', array_keys($this->duplicateMap))->delete();

        // 3. Add unique constraint on nama
        Schema::table('unit_sekolah', function (Blueprint $table) {
            $table->unique('nama');
        });
    }

    public function down(): void
    {
        Schema::table('unit_sekolah', function (Blueprint $table) {
            $table->dropUnique(['nama']);
        });
    }
};
