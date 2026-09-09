<?php

namespace App\Services;

use App\Models\Jadwal;
use App\Models\MataPelajaran;
use App\Models\PegawaiMapel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Gabungkan mata_pelajaran duplikat (nama sama, case-insensitive & trim).
 * Keep = row dengan relasi terbanyak; relasi row sedikit dipindahkan:
 * pivot pegawai_mapel di-repoint, jadwal yang menunjuk pivot lama
 * di-repoint ke pivot keep, lalu dup dihapus.
 */
class MapelDedupService
{
    /**
     * @return array{merged: int, deleted: int} jumlah grup digabung / row dihapus
     */
    public function dedup(): array
    {
        $merged = 0;
        $deleted = 0;

        // Grup berdasarkan nama (case-insensitive, trim).
        $groups = MataPelajaran::all()
            ->groupBy(fn ($m) => mb_strtolower(trim($m->nama)))
            ->filter(fn ($g) => $g->count() > 1);

        foreach ($groups as $group) {
            // Skor = jumlah relasi (pivot + jadwal via pivot).
            $scored = $group->map(function ($m) {
                $pivots = PegawaiMapel::where('mata_pelajaran_id', $m->id)->pluck('id');
                $jadwal = Jadwal::whereIn('pegawai_mapel_id', $pivots)->count();

                return ['mapel' => $m, 'pivots' => $pivots, 'score' => $pivots->count() + $jadwal];
            })->sortByDesc('score')->values();

            $keep = $scored[0];
            $dups = $scored->slice(1);

            foreach ($dups as $dup) {
                $this->mergeInto($dup, $keep);
                $deleted++;
            }
            $merged++;
        }

        return ['merged' => $merged, 'deleted' => $deleted];
    }

    protected function mergeInto(array $dup, array $keep): void
    {
        DB::transaction(function () use ($dup, $keep) {
            $keepPivotKeys = PegawaiMapel::where('mata_pelajaran_id', $keep['mapel']->id)
                ->get()
                ->map(fn ($p) => $p->pegawai_id.'_'.$p->unit_sekolah_id)
                ->all();

            foreach (PegawaiMapel::where('mata_pelajaran_id', $dup['mapel']->id)->get() as $pivot) {
                $key = $pivot->pegawai_id.'_'.$pivot->unit_sekolah_id;

                if (! in_array($key, $keepPivotKeys, true)) {
                    // Tidak bentrok — repoint pivot ke mapel keep.
                    $pivot->update(['mata_pelajaran_id' => $keep['mapel']->id]);
                    $keepPivotKeys[] = $key;

                    continue;
                }

                // Bentrok unique (pegawai+unit sama di kedua mapel): pindahkan
                // jadwal pivot dup ke pivot keep yang cocok, hapus pivot dup.
                $keepPivot = PegawaiMapel::where('mata_pelajaran_id', $keep['mapel']->id)
                    ->where('pegawai_id', $pivot->pegawai_id)
                    ->where('unit_sekolah_id', $pivot->unit_sekolah_id)
                    ->first();

                if ($keepPivot) {
                    Jadwal::where('pegawai_mapel_id', $pivot->id)
                        ->update(['pegawai_mapel_id' => $keepPivot->id]);
                }
                $pivot->delete();
            }

            Log::info('Mapel dedup', [
                'deleted' => $dup['mapel']->nama.' (#'.$dup['mapel']->id.')',
                'kept' => $keep['mapel']->nama.' (#'.$keep['mapel']->id.')',
            ]);
            $dup['mapel']->delete();
        });
    }
}
