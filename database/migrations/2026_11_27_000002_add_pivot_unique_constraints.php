<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->dedupPegawaiUnit();
        $this->dedupPegawaiKomponen();

        Schema::table('pegawai_unit', function (Blueprint $table) {
            if (! Schema::hasIndex('pegawai_unit', 'pegawai_unit_pegawai_unit_jabatan_unique')) {
                $table->unique(['pegawai_id', 'unit_sekolah_id', 'jabatan_id'], 'pegawai_unit_pegawai_unit_jabatan_unique');
            }
        });

        Schema::table('pegawai_komponen_gaji', function (Blueprint $table) {
            if (! Schema::hasIndex('pegawai_komponen_gaji', 'pegawai_komponen_unique')) {
                $table->unique(['pegawai_id', 'komponen_gaji_id'], 'pegawai_komponen_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pegawai_komponen_gaji', function (Blueprint $table) {
            if (Schema::hasIndex('pegawai_komponen_gaji', 'pegawai_komponen_unique')) {
                $table->dropUnique('pegawai_komponen_unique');
            }
        });

        Schema::table('pegawai_unit', function (Blueprint $table) {
            if (Schema::hasIndex('pegawai_unit', 'pegawai_unit_pegawai_unit_jabatan_unique')) {
                $table->dropUnique('pegawai_unit_pegawai_unit_jabatan_unique');
            }
        });
    }

    private function dedupPegawaiUnit(): void
    {
        $dupes = DB::table('pegawai_unit')
            ->select('pegawai_id', 'unit_sekolah_id', 'jabatan_id')
            ->groupBy('pegawai_id', 'unit_sekolah_id', 'jabatan_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($dupes as $d) {
            $keepId = DB::table('pegawai_unit')
                ->where('pegawai_id', $d->pegawai_id)
                ->where('unit_sekolah_id', $d->unit_sekolah_id)
                ->where('jabatan_id', $d->jabatan_id)
                ->orderByDesc('is_primary')
                ->orderBy('id')
                ->value('id');

            DB::table('pegawai_unit')
                ->where('pegawai_id', $d->pegawai_id)
                ->where('unit_sekolah_id', $d->unit_sekolah_id)
                ->where('jabatan_id', $d->jabatan_id)
                ->where('id', '<>', $keepId)
                ->delete();
        }
    }

    private function dedupPegawaiKomponen(): void
    {
        $dupes = DB::table('pegawai_komponen_gaji')
            ->select('pegawai_id', 'komponen_gaji_id')
            ->groupBy('pegawai_id', 'komponen_gaji_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($dupes as $d) {
            $rows = DB::table('pegawai_komponen_gaji')
                ->where('pegawai_id', $d->pegawai_id)
                ->where('komponen_gaji_id', $d->komponen_gaji_id)
                ->orderByDesc('updated_at')
                ->orderByDesc('nominal')
                ->get();

            $keep = (array) $rows->shift();

            DB::table('pegawai_komponen_gaji')
                ->where('pegawai_id', $d->pegawai_id)
                ->where('komponen_gaji_id', $d->komponen_gaji_id)
                ->delete();

            DB::table('pegawai_komponen_gaji')->insert($keep);
        }
    }
};
