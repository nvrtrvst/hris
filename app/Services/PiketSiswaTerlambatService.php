<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\LaporanPiket;
use App\Models\PiketPetugas;
use App\Models\PiketPos;
use App\Models\PiketPosLog;
use App\Models\PiketSesi;
use App\Models\PiketSiswaTerlambat;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PiketSiswaTerlambatService
{
    public static function add(PiketSesi $sesi, PiketPetugas $petugas, array $data, User $user): PiketSiswaTerlambat
    {
        return DB::transaction(function () use ($sesi, $petugas, $data, $user) {
            $exists = PiketSiswaTerlambat::where('unit_sekolah_id', $sesi->unit_sekolah_id)
                ->whereDate('tanggal', $sesi->tanggal)
                ->where('nis', $data['nis'])
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages(['nis' => 'Siswa ini sudah tercatat terlambat hari ini.']);
            }

            $pos = self::gatePos($sesi, $petugas);

            $late = PiketSiswaTerlambat::create([
                'unit_sekolah_id' => $sesi->unit_sekolah_id,
                'piket_sesi_id' => $sesi->id,
                'piket_pos_id' => $pos?->id,
                'pegawai_id' => $petugas->pegawai_id,
                'tanggal' => $sesi->tanggal,
                'class_id' => $data['class_id'],
                'class_label' => $data['class_label'],
                'nis' => $data['nis'],
                'nama_snapshot' => $data['nama_snapshot'],
                'jam_terlambat' => $data['jam_terlambat'],
                'keterangan' => $data['keterangan'] ?? null,
                'created_by' => $user->id,
            ]);

            self::sync($sesi, $petugas);

            AuditLog::log($late, 'catat_siswa_terlambat', [], [
                'nis' => $late->nis,
                'class_label' => $late->class_label,
                'jam_terlambat' => $late->jam_terlambat,
            ]);

            return $late;
        });
    }

    public static function remove(PiketSiswaTerlambat $late, User $user): void
    {
        DB::transaction(function () use ($late, $user) {
            $sesi = $late->sesi;
            $petugas = $sesi ? PiketService::petugasFor($sesi, (int) $late->pegawai_id) : null;

            $late->delete();

            if ($sesi) {
                self::sync($sesi, $petugas);
            }

            AuditLog::log($late, 'hapus_siswa_terlambat', [
                'nis' => $late->nis,
                'class_label' => $late->class_label,
            ], ['oleh' => $user->id]);
        });
    }

    public static function zero(PiketSesi $sesi, PiketPetugas $petugas): void
    {
        $count = PiketSiswaTerlambat::where('piket_sesi_id', $sesi->id)->count();
        if ($count > 0) {
            throw ValidationException::withMessages(['terlambat' => 'Hapus entry siswa terlambat terlebih dahulu.']);
        }

        DB::transaction(function () use ($sesi, $petugas) {
            self::sync($sesi, $petugas);
        });
    }

    public static function sync(PiketSesi $sesi, ?PiketPetugas $petugas): void
    {
        $count = PiketSiswaTerlambat::where('piket_sesi_id', $sesi->id)->count();

        if (PiketService::isAreaMode($sesi->unitSekolah)) {
            $pos = self::gatePos($sesi, $petugas);
            if ($pos) {
                PiketService::recordPos(
                    $sesi,
                    $pos,
                    (int) ($petugas?->pegawai_id ?? 0),
                    $count === 0,
                    $count.' siswa terlambat',
                    'petugas',
                );
                PiketPosLog::where('sesi_id', $sesi->id)
                    ->where('pos_id', $pos->id)
                    ->update(['jumlah' => $count]);
            }

            return;
        }

        if (! $petugas) {
            return;
        }

        $laporan = LaporanPiket::where('pegawai_id', $petugas->pegawai_id)
            ->whereDate('tanggal', $sesi->tanggal)
            ->first();

        if (! $laporan) {
            $laporan = LaporanPiket::create([
                'pegawai_id' => $petugas->pegawai_id,
                'unit_sekolah_id' => $sesi->unit_sekolah_id,
                'tanggal' => $sesi->tanggal,
                'status' => LaporanPiket::STATUS_DRAFT,
                'items' => [],
            ]);
        }

        $items = $laporan->items ?? [];
        $items[LaporanPiket::ITEM_GERBANG] = array_merge($items[LaporanPiket::ITEM_GERBANG] ?? [], [
            'terlambat_count' => $count,
            'done_at' => Carbon::now()->format('H:i:s'),
        ]);
        $laporan->items = $items;
        $laporan->save();
    }

    private static function gatePos(PiketSesi $sesi, ?PiketPetugas $petugas): ?PiketPos
    {
        if ($petugas?->pos_id) {
            $pos = PiketPos::where('unit_sekolah_id', $sesi->unit_sekolah_id)
                ->where('aktif', true)
                ->find($petugas->pos_id);

            if ($pos) {
                return $pos;
            }
        }

        return PiketPos::where('unit_sekolah_id', $sesi->unit_sekolah_id)
            ->where('aktif', true)
            ->where('nama', 'like', '%gerbang%')
            ->first();
    }
}
