<?php

namespace App\Services;

use App\Models\Jadwal;
use App\Models\LaporanPiket;
use App\Models\Pegawai;
use App\Models\PiketArea;
use App\Models\PiketItemTemplate;
use App\Models\PiketPenugasan;
use App\Models\PiketPetugas;
use App\Models\PiketPos;
use App\Models\PiketPosLog;
use App\Models\PiketSesi;
use App\Models\UnitSekolah;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PiketService
{
    public const MODE_SIMPLE = 'simple';

    public const MODE_AREA = 'area';

    public static function isAreaMode(?UnitSekolah $unit): bool
    {
        return $unit?->piket_mode === self::MODE_AREA;
    }

    /**
     * Sesi hari ini untuk slot piket; dibuat sekali dan menautkan petugas.
     */
    public static function sessionFor(Pegawai $pegawai, Jadwal $slot): PiketSesi
    {
        $tanggal = Carbon::today()->toDateString();
        $mulai = substr((string) $slot->jam_mulai, 0, 5);
        $selesai = substr((string) $slot->jam_selesai, 0, 5);

        $sesi = PiketSesi::firstOrCreate(
            [
                'unit_sekolah_id' => $slot->unit_sekolah_id,
                'tanggal' => $tanggal,
                'jam_mulai' => $mulai,
                'jam_selesai' => $selesai,
            ],
            ['status' => 'open'],
        );

        $penugasan = PiketPenugasan::with('pegawai')
            ->where('unit_sekolah_id', $slot->unit_sekolah_id)
            ->where('aktif', true)
            ->where(fn ($q) => $q->whereNull('tahun_ajaran')->orWhere('tahun_ajaran', $slot->tahun_ajaran))
            ->where(fn ($q) => $q->whereNull('semester')->orWhere('semester', (string) $slot->semester))
            ->get();

        if ($penugasan->isEmpty()) {
            // Fallback: semua baris jadwal piket pada jam yang sama.
            $jadwalPetugas = Jadwal::where('unit_sekolah_id', $slot->unit_sekolah_id)
                ->where('jenis_jadwal', 'piket')
                ->where('hari', $slot->hari)
                ->where('jam_mulai', $slot->jam_mulai)
                ->pluck('pegawai_id');

            foreach ($jadwalPetugas as $pegawaiId) {
                self::linkPetugas($sesi, (int) $pegawaiId, null, null, 'area');
            }
        } else {
            foreach ($penugasan as $row) {
                self::linkPetugas($sesi, (int) $row->pegawai_id, $row->area_id, $row->pos_id, $row->peran ?: 'area');
            }
        }

        self::linkPetugas($sesi, $pegawai->id, null, null, 'area');

        return $sesi->fresh('petugas');
    }

    public static function linkPetugas(PiketSesi $sesi, int $pegawaiId, ?int $areaId, ?int $posId, string $peran): PiketPetugas
    {
        $tanggal = $sesi->tanggal instanceof Carbon ? $sesi->tanggal->toDateString() : (string) $sesi->tanggal;

        $laporan = LaporanPiket::firstOrCreate(
            ['pegawai_id' => $pegawaiId, 'tanggal' => $tanggal],
            [
                'jadwal_id' => null,
                'unit_sekolah_id' => $sesi->unit_sekolah_id,
                'status' => LaporanPiket::STATUS_DRAFT,
                'items' => [],
            ],
        );

        if ($laporan->sesi_id === null) {
            $laporan->forceFill([
                'sesi_id' => $sesi->id,
                'area_id' => $areaId,
                'peran' => $peran,
            ])->save();
        }

        return PiketPetugas::firstOrCreate(
            ['sesi_id' => $sesi->id, 'pegawai_id' => $pegawaiId],
            ['area_id' => $areaId, 'pos_id' => $posId, 'peran' => $peran, 'laporan_piket_id' => $laporan->id],
        );
    }

    public static function petugasFor(PiketSesi $sesi, int $pegawaiId): ?PiketPetugas
    {
        return PiketPetugas::where('sesi_id', $sesi->id)
            ->where('pegawai_id', $pegawaiId)
            ->first();
    }

    /** @return array<int, array<string, mixed>> */
    public static function activeTemplates(?UnitSekolah $unit, ?PiketPetugas $petugas): array
    {
        if (! $unit) {
            return [];
        }

        return PiketItemTemplate::where('unit_sekolah_id', $unit->id)
            ->where('aktif', true)
            ->orderBy('urutan')
            ->get()
            ->filter(function (PiketItemTemplate $t) use ($petugas) {
                return match ($t->scope) {
                    'area' => $petugas?->area_id !== null,
                    'pos' => $petugas?->pos_id !== null,
                    default => true,
                };
            })
            ->map(fn (PiketItemTemplate $t) => [
                'kode' => $t->kode,
                'label' => $t->label,
                'seksi' => $t->seksi,
                'scope' => $t->scope,
                'tipe' => $t->tipe,
                'wajib' => $t->wajib,
                'butuh_foto' => $t->butuh_foto,
                'urutan' => $t->urutan,
            ])
            ->values()
            ->all();
    }

    /** @return list<string> */
    public static function requiredCodes(?UnitSekolah $unit, ?PiketPetugas $petugas): array
    {
        return collect(self::activeTemplates($unit, $petugas))
            ->where('wajib', true)
            ->pluck('kode')
            ->values()
            ->all();
    }

    public static function posFor(?UnitSekolah $unit, ?PiketPetugas $petugas): array
    {
        if (! $unit || ! $petugas) {
            return [];
        }

        return PiketPos::where('unit_sekolah_id', $unit->id)
            ->where('aktif', true)
            ->where(function ($q) use ($petugas) {
                $q->whereNull('area_id');
                if ($petugas->area_id) {
                    $q->orWhere('area_id', $petugas->area_id);
                }
            })
            ->orderBy('jam_mulai')
            ->get()
            ->map(fn (PiketPos $pos) => [
                'id' => $pos->id,
                'nama' => $pos->nama,
                'jam_mulai' => $pos->jam_mulai,
                'jam_selesai' => $pos->jam_selesai,
                'is_wajib' => $pos->is_wajib,
            ])
            ->all();
    }

    public static function posLogs(PiketSesi $sesi): array
    {
        return PiketPosLog::where('sesi_id', $sesi->id)
            ->get()
            ->mapWithKeys(fn (PiketPosLog $log) => [$log->pos_id => [
                'done_at' => $log->done_at?->format('H:i'),
                'nihil' => (bool) $log->nihil,
                'catatan' => $log->catatan,
                'sumber' => $log->sumber,
                'alasan_override' => $log->alasan_override,
            ]])
            ->all();
    }

    /** Posisi wajib yang belum punya catatan. */
    public static function unservedRequiredPos(PiketSesi $sesi): array
    {
        $served = PiketPosLog::where('sesi_id', $sesi->id)->pluck('pos_id')->all();

        return PiketPos::where('unit_sekolah_id', $sesi->unit_sekolah_id)
            ->where('aktif', true)
            ->where('is_wajib', true)
            ->whereNotIn('id', $served)
            ->pluck('nama')
            ->all();
    }

    public static function recordPos(PiketSesi $sesi, PiketPos $pos, int $pegawaiId, bool $nihil, ?string $catatan, string $sumber = 'manual', ?int $overrideBy = null, ?string $alasan = null): PiketPosLog
    {
        return PiketPosLog::updateOrCreate(
            ['sesi_id' => $sesi->id, 'pos_id' => $pos->id],
            [
                'petugas_id' => $pegawaiId,
                'done_at' => now(),
                'nihil' => $nihil,
                'catatan' => $catatan,
                'sumber' => $sumber,
                'override_by' => $overrideBy,
                'alasan_override' => $alasan,
            ],
        );
    }

    public static function markPosUnavailable(PiketSesi $sesi, PiketPos $pos, int $userId, string $alasan): PiketPosLog
    {
        return PiketPosLog::updateOrCreate(
            ['sesi_id' => $sesi->id, 'pos_id' => $pos->id],
            [
                'petugas_id' => null,
                'done_at' => now(),
                'nihil' => true,
                'catatan' => 'Pos dinonaktifkan hari ini.',
                'sumber' => 'nonaktif',
                'override_by' => $userId,
                'alasan_override' => $alasan,
            ],
        );
    }

    public static function sessionBlockReason(?LaporanPiket $laporan): ?string
    {
        if (! $laporan?->sesi_id) {
            return null;
        }

        $sesi = PiketSesi::find($laporan->sesi_id);
        if (! $sesi) {
            return null;
        }

        $unserved = self::unservedRequiredPos($sesi);

        if ($unserved !== []) {
            return 'Pos belum tercatat: '.implode(', ', $unserved).'.';
        }

        return null;
    }

    public static function teamProgress(PiketSesi $sesi): array
    {
        return PiketPetugas::with(['pegawai:id,nama_lengkap', 'laporan', 'area:id,nama'])
            ->where('sesi_id', $sesi->id)
            ->get()
            ->map(fn (PiketPetugas $p) => [
                'pegawai_id' => $p->pegawai_id,
                'nama' => $p->pegawai?->nama_lengkap,
                'area' => $p->area?->nama,
                'peran' => $p->peran,
                'selesai' => $p->laporan ? $p->laporan->doneCount() : 0,
                'submitted' => $p->laporan?->status === LaporanPiket::STATUS_SUBMITTED,
            ])
            ->values()
            ->all();
    }

    /** Label kelas yang dipetakan ke area petugas. */
    public static function kelasArea(?UnitSekolah $unit, ?int $areaId): array
    {
        if (! $unit || ! $areaId) {
            return [];
        }

        return DB::table('piket_area_kelas')
            ->where('unit_sekolah_id', $unit->id)
            ->where('area_id', $areaId)
            ->pluck('kelas_label')
            ->all();
    }

    public static function areaBagi(Pegawai $pegawai, ?Jadwal $slot = null): ?PiketArea
    {
        if (! $slot) {
            return null;
        }

        $sesi = PiketSesi::where('unit_sekolah_id', $slot->unit_sekolah_id)
            ->where('tanggal', Carbon::today()->toDateString())
            ->where('jam_mulai', substr((string) $slot->jam_mulai, 0, 5))
            ->first();

        if (! $sesi) {
            return null;
        }

        $petugas = self::petugasFor($sesi, $pegawai->id);

        return $petugas?->area_id ? PiketArea::find($petugas->area_id) : null;
    }
}
