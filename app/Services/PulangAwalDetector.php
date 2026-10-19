<?php

namespace App\Services;

use App\Models\PengajuanPulangCepat;
use App\Models\Presensi;

/**
 * Menandai presensi yang pulang lebih awal dari jam yang diharapkan.
 * Tidak memblokir apa pun — hanya mencatat is_pulang_awal + menit_pulang_awal
 * dan apakah ada Izin Pulang Cepat yang disetujui (pulang_awal_diizinkan).
 */
class PulangAwalDetector
{
    /** Batas toleransi bawaan bila unit belum mengatur (menit). */
    public const TOLERANSI_DEFAULT = 0;

    /** Jeda pulang guru setelah JP terakhir bila unit belum mengatur (menit). */
    public const PULANG_SEBELUM_DEFAULT = 20;

    public function apply(Presensi $presensi): void
    {
        if ($presensi->is_lembur || $presensi->is_tugas_luar) {
            $this->clear($presensi);

            return;
        }

        $unit = $presensi->unitSekolah;
        if (! $unit || ! $presensi->tanggal) {
            $this->clear($presensi);

            return;
        }

        $expected = $this->expectedTime($presensi, $unit);
        if (! $expected || ! $presensi->jam_keluar) {
            $this->clear($presensi);

            return;
        }

        $selisih = (int) floor((strtotime('1970-01-01 '.$expected) - strtotime('1970-01-01 '.$presensi->jam_keluar)) / 60);
        $menit = max(0, $selisih - (int) ($unit->toleransi_menit ?? self::TOLERANSI_DEFAULT));

        $presensi->is_pulang_awal = $menit > 0;
        $presensi->menit_pulang_awal = $menit > 0 ? $menit : null;
        $presensi->pulang_awal_diizinkan = $menit > 0
            && PengajuanPulangCepat::disetujuiPada($presensi->pegawai_id, $presensi->tanggal) !== null;
    }

    /** Jam pulang yang diharapkan: jadwal mengajar dipotong pulang_sebelum_menit, kantor dari jam_pulang_kantor. */
    private function expectedTime(Presensi $presensi, $unit): ?string
    {
        $jadwal = $presensi->jadwal;

        if ($jadwal) {
            $menit = $jadwal->jenis_jadwal === 'mengajar'
                ? (int) ($unit->pulang_sebelum_menit ?? self::PULANG_SEBELUM_DEFAULT)
                : 0;

            $normal = $this->shiftMinutes($jadwal->jam_selesai, -$menit);
        } else {
            $normal = $unit->jam_pulang_kantor ? $this->shiftMinutes($unit->jam_pulang_kantor, 0) : null;
        }

        if (! $normal) {
            return null;
        }

        // Izin pulang cepat disetujui = batas bawah pulang: sampai di jam izin
        // bukan "pulang awal". Izin tak pernah memperketat (min dengan normal).
        $izin = PengajuanPulangCepat::disetujuiPada($presensi->pegawai_id, $presensi->tanggal)?->jam_diminta;

        if ($izin && strtotime('1970-01-01 '.$izin) < strtotime('1970-01-01 '.$normal)) {
            return $izin;
        }

        return $normal;
    }

    private function shiftMinutes(?string $time, int $minutes): ?string
    {
        if (! $time) {
            return null;
        }

        return date('H:i:s', strtotime('1970-01-01 '.$time) + ($minutes * 60));
    }

    private function clear(Presensi $presensi): void
    {
        $presensi->is_pulang_awal = false;
        $presensi->menit_pulang_awal = null;
        $presensi->pulang_awal_diizinkan = false;
    }
}
