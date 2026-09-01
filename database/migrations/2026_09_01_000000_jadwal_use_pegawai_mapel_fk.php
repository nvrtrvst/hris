<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Unique constraint on pegawai_mapel to prevent duplicates.
        DB::table('pegawai_mapel')
            ->select('pegawai_id', 'mata_pelajaran_id', 'unit_sekolah_id', DB::raw('MIN(id) as keep_id'))
            ->groupBy('pegawai_id', 'mata_pelajaran_id', 'unit_sekolah_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->each(function ($dup) {
                DB::table('pegawai_mapel')
                    ->where('pegawai_id', $dup->pegawai_id)
                    ->where('mata_pelajaran_id', $dup->mata_pelajaran_id)
                    ->where('unit_sekolah_id', $dup->unit_sekolah_id)
                    ->where('id', '!=', $dup->keep_id)
                    ->delete();
            });

        Schema::table('pegawai_mapel', function (Blueprint $table) {
            $table->unique(['pegawai_id', 'mata_pelajaran_id', 'unit_sekolah_id'], 'pm_pml_unit_uniq');
        });

        // 2. Add pegawai_mapel_id to jadwal (nullable first for backfill).
        Schema::table('jadwal', function (Blueprint $table) {
            $table->unsignedBigInteger('pegawai_mapel_id')->nullable()->after('mata_pelajaran_id');
        });

        // 3. Backfill: for each jadwal with mata_pelajaran_id, find or create pegawai_mapel.
        $jadwals = DB::table('jadwal')
            ->whereNotNull('mata_pelajaran_id')
            ->select('id', 'pegawai_id', 'mata_pelajaran_id', 'unit_sekolah_id')
            ->get();

        foreach ($jadwals as $jadwal) {
            $pivot = DB::table('pegawai_mapel')
                ->where('pegawai_id', $jadwal->pegawai_id)
                ->where('mata_pelajaran_id', $jadwal->mata_pelajaran_id)
                ->where('unit_sekolah_id', $jadwal->unit_sekolah_id)
                ->first();

            if (! $pivot) {
                $pivotId = DB::table('pegawai_mapel')->insertGetId([
                    'pegawai_id' => $jadwal->pegawai_id,
                    'mata_pelajaran_id' => $jadwal->mata_pelajaran_id,
                    'unit_sekolah_id' => $jadwal->unit_sekolah_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $pivotId = $pivot->id;
            }

            DB::table('jadwal')->where('id', $jadwal->id)->update(['pegawai_mapel_id' => $pivotId]);
        }

        // 4. Drop mata_pelajaran_id column.
        Schema::table('jadwal', function (Blueprint $table) {
            $table->dropForeign(['mata_pelajaran_id']);
            $table->dropColumn('mata_pelajaran_id');
        });

        // 5. Add FK constraint on pegawai_mapel_id.
        Schema::table('jadwal', function (Blueprint $table) {
            $table->foreign('pegawai_mapel_id')->references('id')->on('pegawai_mapel')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('jadwal', function (Blueprint $table) {
            $table->dropForeign(['pegawai_mapel_id']);
            $table->dropColumn('pegawai_mapel_id');
        });

        Schema::table('jadwal', function (Blueprint $table) {
            $table->foreignId('mata_pelajaran_id')->nullable()->constrained('mata_pelajaran')->nullOnDelete();
        });

        Schema::table('pegawai_mapel', function (Blueprint $table) {
            $table->dropUnique('pm_pml_unit_uniq');
        });
    }
};
