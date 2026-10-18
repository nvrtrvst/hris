<?php

namespace App\Services;

use App\Models\Jadwal;
use App\Models\Presensi;
use Carbon\Carbon;

/**
 * Sumber tunggal bobot JP: 1 baris = durasi ÷ durasi_jp unit (default 45 menit).
 * Dipakai export rekap mengajar + metrik beban mengajar mingguan.
 */
class JpCalculator
{
    /** Target beban mengajar guru per minggu (JP). */
    public const TARGET_JP_MINGGU = 24;

    public static function weight(Presensi $presensi): float
    {
        return $presensi->jadwal ? self::weightFromJadwal($presensi->jadwal) : 0.0;
    }

    public static function weightFromJadwal(Jadwal $jadwal): float
    {
        $durasiJp = (int) ($jadwal->unitSekolah?->durasi_jp ?? 45);
        if ($durasiJp <= 0) {
            $durasiJp = 45;
        }
        $menit = Carbon::parse($jadwal->jam_mulai)->diffInMinutes(Carbon::parse($jadwal->jam_selesai));

        return round(max(0, $menit) / $durasiJp, 2);
    }
}
