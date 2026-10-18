<?php

namespace App\Support;

use App\Constants\PresensiMessages;
use App\Models\HariLibur;
use App\Models\Pegawai;
use App\Models\UnitSekolah;
use Carbon\Carbon;

/**
 * Sumber tunggal aturan "boleh presensi atau tidak" di portal mobile.
 * Senin–Jumat selalu hari kerja. Sabtu/Minggu mengikuti jam kerja unit.
 * Tanggal yang terdaftar di hari_libur (nasional atau unit) selalu libur.
 */
class HariKerja
{
    private const HARI_MAP = [
        'Sunday' => 'Minggu',
        'Monday' => 'Senin',
        'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis',
        'Friday' => 'Jumat',
        'Saturday' => 'Sabtu',
    ];

    /** Nama hari Indonesia dari sebuah tanggal (default: hari ini). */
    public static function hariIndo(?Carbon $tanggal = null): string
    {
        return self::HARI_MAP[($tanggal ?? Carbon::now())->format('l')];
    }

    /**
     * Unit beroperasi pada hari tersebut?
     * Senin–Jumat selalu hari kerja (unit dianggap buka, bahkan tanpa data unit).
     * Sabtu/Minggu mengikuti jam kerja unit; $unit null di akhir pekan = tutup.
     */
    public static function unitBukaHari(?UnitSekolah $unit, string $hari): bool
    {
        if (in_array($hari, ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'], true)) {
            return true;
        }

        if (! $unit) {
            return false;
        }

        return (bool) ($hari === 'Sabtu' ? $unit->jam_kerja_sabtu_mulai : $unit->jam_kerja_minggu_mulai);
    }

    /** Unit pegawai (primary diutamakan) yang beroperasi pada hari tersebut. */
    public static function unitBuka(Pegawai $pegawai, string $hari): ?UnitSekolah
    {
        $units = $pegawai->relationLoaded('units') ? $pegawai->units : $pegawai->units()->get();

        return $units->sortByDesc('pivot.is_primary')->first(fn (UnitSekolah $unit) => self::unitBukaHari($unit, $hari));
    }

    /** Hari libur (nasional atau unit pegawai) pada tanggal tersebut. */
    public static function liburUntuk(Pegawai $pegawai, Carbon $tanggal): ?HariLibur
    {
        $unitIds = ($pegawai->relationLoaded('units') ? $pegawai->units : $pegawai->units()->get())
            ->pluck('id')->filter()->unique()->all();

        return HariLibur::whereDate('tanggal', $tanggal->toDateString())
            ->where(function ($q) use ($unitIds) {
                $q->whereNull('unit_sekolah_id');
                if ($unitIds) {
                    $q->orWhereIn('unit_sekolah_id', $unitIds);
                }
            })
            ->first();
    }

    /**
     * Alasan presensi ditolak (string pesan) atau null bila boleh.
     * $unitOverride dipakai saat jalur presensi terikat jadwal unit tertentu
     * (slide / mengajar) agar cek Sabtu-Minggu memakai unit jadwal itu.
     * $adaJadwal = true melewati cek jam kerja unit: jadwal mengajar/piket adalah
     * bukti unit beroperasi hari itu. Hari libur tetap memblokir.
     */
    public static function alasanTidakBisaPresensi(Pegawai $pegawai, ?Carbon $tanggal = null, ?UnitSekolah $unitOverride = null, bool $adaJadwal = false): ?string
    {
        $tanggal ??= Carbon::now();
        $hari = self::hariIndo($tanggal);

        if (! $adaJadwal && in_array($hari, ['Sabtu', 'Minggu'], true)) {
            $buka = $unitOverride
                ? self::unitBukaHari($unitOverride, $hari)
                : self::unitBuka($pegawai, $hari) !== null;
            if (! $buka) {
                return sprintf(PresensiMessages::HARI_TIDAK_AKTIF, $hari);
            }
        }

        $libur = self::liburUntuk($pegawai, $tanggal);
        if ($libur) {
            return sprintf(PresensiMessages::LIBUR_NASIONAL, $libur->nama ?: 'Hari libur');
        }

        return null;
    }
}
