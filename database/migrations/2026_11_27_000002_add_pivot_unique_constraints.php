<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

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
                ->orderByRaw('CASE WHEN nominal IS NULL THEN 1 ELSE 0 END')
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get();

            $nominals = $rows
                ->pluck('nominal')
                ->filter(fn ($nominal) => $nominal !== null)
                ->map(fn ($nominal) => $this->normalizeNominal((string) $nominal))
                ->unique()
                ->values();

            if ($nominals->count() > 1) {
                throw new RuntimeException(sprintf(
                    'Conflicting nominal overrides for pegawai_id=%d, komponen_gaji_id=%d. Resolve duplicate rows before migrating.',
                    $d->pegawai_id,
                    $d->komponen_gaji_id,
                ));
            }

            $keep = $rows->first();
            $keepNominal = $rows->firstWhere('nominal', '!=', null)?->nominal;
            $createdAt = $rows->pluck('created_at')->filter()->sort()->first();
            $updatedAt = $rows->pluck('updated_at')->filter()->sortDesc()->first();

            DB::table('pegawai_komponen_gaji')
                ->where('pegawai_id', $d->pegawai_id)
                ->where('komponen_gaji_id', $d->komponen_gaji_id)
                ->where('id', '<>', $keep->id)
                ->delete();

            DB::table('pegawai_komponen_gaji')
                ->where('id', $keep->id)
                ->update([
                    'nominal' => $keepNominal,
                    'created_at' => $createdAt ?? $keep->created_at,
                    'updated_at' => $updatedAt ?? $keep->updated_at,
                ]);
        }
    }

    private function normalizeNominal(string $nominal): string
    {
        $normalized = str_contains($nominal, '.')
            ? rtrim(rtrim($nominal, '0'), '.')
            : $nominal;

        return $normalized === '' || $normalized === '-0' ? '0' : $normalized;
    }
};
