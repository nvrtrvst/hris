<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('laporan_piket', function (Blueprint $table) {
            $table->index(['pegawai_id', 'tanggal'], 'laporan_piket_pegawai_tanggal_idx');
        });

        Schema::table('laporan_piket', function (Blueprint $table) {
            $table->dropUnique('laporan_piket_pegawai_id_tanggal_unique');
        });

        DB::transaction(fn () => $this->pisahkanLaporanSesi());

        Schema::table('laporan_piket', function (Blueprint $table) {
            $table->unique(['sesi_id', 'pegawai_id'], 'laporan_piket_sesi_pegawai_unique');
        });

        Schema::table('piket_sesi', function (Blueprint $table) {
            $table->index(['tanggal', 'unit_sekolah_id'], 'piket_sesi_tanggal_unit_idx');
        });

        Schema::table('piket_petugas', function (Blueprint $table) {
            $table->index(['pegawai_id', 'sesi_id'], 'piket_petugas_pegawai_sesi_idx');
        });

        Schema::table('piket_round', function (Blueprint $table) {
            $table->index(['sesi_id', 'selesai_at'], 'piket_round_sesi_selesai_idx');
        });

        Schema::table('jadwal', function (Blueprint $table) {
            $table->index(['unit_sekolah_id', 'hari', 'jenis_jadwal'], 'jadwal_unit_hari_jenis_idx');
        });

        Schema::table('presensi', function (Blueprint $table) {
            $table->index(['tanggal', 'jadwal_id'], 'presensi_tanggal_jadwal_idx');
        });
    }

    public function down(): void
    {
        Schema::table('presensi', function (Blueprint $table) {
            $table->dropIndex('presensi_tanggal_jadwal_idx');
        });

        Schema::table('jadwal', function (Blueprint $table) {
            $table->dropIndex('jadwal_unit_hari_jenis_idx');
        });

        Schema::table('piket_round', function (Blueprint $table) {
            $table->dropIndex('piket_round_sesi_selesai_idx');
        });

        Schema::table('piket_petugas', function (Blueprint $table) {
            $table->dropIndex('piket_petugas_pegawai_sesi_idx');
        });

        Schema::table('piket_sesi', function (Blueprint $table) {
            $table->dropIndex('piket_sesi_tanggal_unit_idx');
        });

        Schema::table('laporan_piket', function (Blueprint $table) {
            $table->dropUnique('laporan_piket_sesi_pegawai_unique');
            $table->unique(['pegawai_id', 'tanggal']);
            $table->dropIndex('laporan_piket_pegawai_tanggal_idx');
        });
    }

    private function pisahkanLaporanSesi(): void
    {
        $rows = DB::table('piket_petugas as pp')
            ->join('laporan_piket as lp', 'lp.id', '=', 'pp.laporan_piket_id')
            ->join('piket_sesi as ps', 'ps.id', '=', 'pp.sesi_id')
            ->select([
                'pp.id as petugas_id',
                'pp.sesi_id',
                'pp.pegawai_id',
                'pp.laporan_piket_id',
                'pp.area_id as petugas_area_id',
                'pp.peran as petugas_peran',
                'lp.sesi_id as laporan_sesi_id',
                'lp.jadwal_id',
                'lp.items',
                'ps.unit_sekolah_id',
                'ps.tanggal',
            ])
            ->whereNotNull('pp.laporan_piket_id')
            ->where(function ($query) {
                $query->whereNull('lp.sesi_id')
                    ->orWhereColumn('lp.sesi_id', '!=', 'pp.sesi_id');
            })
            ->orderBy('pp.id')
            ->get();

        foreach ($rows as $row) {
            $laporanSesi = DB::table('laporan_piket')
                ->where('sesi_id', $row->sesi_id)
                ->where('pegawai_id', $row->pegawai_id)
                ->first();

            if ($laporanSesi) {
                DB::table('piket_petugas')
                    ->where('id', $row->petugas_id)
                    ->update(['laporan_piket_id' => $laporanSesi->id]);

                continue;
            }

            if ($row->laporan_sesi_id === null) {
                DB::table('laporan_piket')
                    ->where('id', $row->laporan_piket_id)
                    ->update([
                        'sesi_id' => $row->sesi_id,
                        'unit_sekolah_id' => $row->unit_sekolah_id,
                        'tanggal' => $row->tanggal,
                        'area_id' => $row->petugas_area_id,
                        'peran' => $row->petugas_peran,
                        'updated_at' => now(),
                    ]);

                continue;
            }

            $laporanBaru = DB::table('laporan_piket')->insertGetId([
                'pegawai_id' => $row->pegawai_id,
                'jadwal_id' => $row->jadwal_id,
                'unit_sekolah_id' => $row->unit_sekolah_id,
                'sesi_id' => $row->sesi_id,
                'area_id' => $row->petugas_area_id,
                'peran' => $row->petugas_peran,
                'tanggal' => $row->tanggal,
                'items' => $row->items,
                'status' => 'draft',
                'submitted_at' => null,
                'unlocked_at' => null,
                'unlocked_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('piket_petugas')
                ->where('id', $row->petugas_id)
                ->update(['laporan_piket_id' => $laporanBaru]);
        }
    }
};
