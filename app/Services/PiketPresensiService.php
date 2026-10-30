<?php

namespace App\Services;

use App\Models\Presensi;
use Carbon\Carbon;

class PiketPresensiService
{
    /**
     * Piket adalah observasi; presensi adalah bukti utama.
     * Konflik ditandai perlu_cek, bukan menimpa presensi.
     *
     * @return array{perlu_cek: bool, catatan: ?string, presensi_status: ?string, presensi_jam: ?string}
     */
    public static function conflict(
        int $pegawaiId,
        string $tanggal,
        ?string $statusAbsen,
        ?string $waktuPiket = null,
    ): array {
        $empty = [
            'perlu_cek' => false,
            'catatan' => null,
            'presensi_status' => null,
            'presensi_jam' => null,
        ];

        if (! in_array($statusAbsen, ['sakit', 'izin', 'cuti', 'alpa'], true)) {
            return $empty;
        }

        $presensi = Presensi::query()
            ->where('pegawai_id', $pegawaiId)
            ->whereDate('tanggal', $tanggal)
            ->whereNotNull('jam_masuk')
            ->whereIn('status', ['hadir', 'telat'])
            ->orderBy('jam_masuk')
            ->first(['status', 'jam_masuk']);

        if (! $presensi) {
            return $empty;
        }

        $jamPresensi = $presensi->jam_masuk ? substr((string) $presensi->jam_masuk, 0, 5) : null;
        $jamPiket = $waktuPiket
            ? (str_contains($waktuPiket, ' ') ? Carbon::parse($waktuPiket)->format('H:i') : substr($waktuPiket, 0, 5))
            : null;
        $labelPiket = ucfirst($statusAbsen);

        return [
            'perlu_cek' => true,
            'catatan' => trim(sprintf(
                'Presensi %s %s; piket mencatat %s%s',
                $presensi->status,
                $jamPresensi ?: '-',
                $labelPiket,
                $jamPiket ? ' pukul '.$jamPiket : '',
            )),
            'presensi_status' => $presensi->status,
            'presensi_jam' => $jamPresensi,
        ];
    }

    public static function hasPresence(int $pegawaiId, string $tanggal): bool
    {
        return Presensi::query()
            ->where('pegawai_id', $pegawaiId)
            ->whereDate('tanggal', Carbon::parse($tanggal)->toDateString())
            ->whereIn('status', ['hadir', 'telat'])
            ->exists();
    }
}
