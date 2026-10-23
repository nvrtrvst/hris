<?php

namespace App\Constants;

use App\Models\PengajuanIzin;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Bobot kehadiran default per status ketika unit tidak mengkonfigurasi
 * kolom bobot sendiri (unit_sekolah.bobot_*).
 * 1.0 = dihitung hari penuh; 0.5 = setengah hari; 0 = tidak dihitung hadir.
 */
class BobotKehadiran
{
    public const SAKIT_SURAT = 1.0;

    public const SAKIT_TANPA_SURAT = 0.5;

    public const IZIN = 0.5;

    public const IZIN_SETENGAH = 0.5;

    public const CUTI = 1.0;

    public const ALPA = 0.0;

    public const DEFAULTS = [
        'sakit_surat' => self::SAKIT_SURAT,
        'sakit_tanpa_surat' => self::SAKIT_TANPA_SURAT,
        'izin' => self::IZIN,
        'izin_setengah' => self::IZIN_SETENGAH,
        'cuti' => self::CUTI,
        'alpa' => self::ALPA,
    ];

    /**
     * Hari-ekuivalen berbobot: hadir/telat 1.0, sisanya sesuai bobot.
     * $setengah (flag dihitung_setengah) diasumsikan subset izin —
     // dipisah agar tidak dihitung dobel (bobot izin + bobot setengah).
     */
    public static function hadirEq(int $hadir, int $telat, int $sakitSurat, int $sakitTanpa, int $izin, int $setengah, int $cuti, array $bobot): float
    {
        $izinFull = max(0, $izin - $setengah);

        return $hadir + $telat
            + $bobot['sakit_surat'] * $sakitSurat
            + $bobot['sakit_tanpa_surat'] * $sakitTanpa
            + $bobot['izin'] * $izinFull
            + $bobot['izin_setengah'] * $setengah
            + $bobot['cuti'] * $cuti;
    }

    /** Persen kehadiran berbobot, dibatasi 0–100. */
    public static function persenBerbobot(float $hadirEq, int $hariKerja): int
    {
        return $hariKerja > 0 ? (int) min(100, round(max(0.0, $hadirEq) * 100 / $hariKerja)) : 0;
    }

    /**
     * Split tanggal sakit (Y-m-d) atas dasar range pengajuan sakit berflag
     * surat dokter (list [mulai, selesai]).
     *
     * @param  iterable<string>  $dates
     * @param  Collection|array  $ranges
     * @return array{surat: int, tanpa: int}
     */
    public static function splitSakitSurat($dates, $ranges): array
    {
        $dates = collect($dates)->values();
        $surat = $dates->filter(function ($d) use ($ranges) {
            foreach ($ranges as $r) {
                if ($d >= $r[0] && $d <= $r[1]) {
                    return true;
                }
            }

            return false;
        })->count();

        return ['surat' => $surat, 'tanpa' => $dates->count() - $surat];
    }

    /**
     * Range pengajuan izin sakit disetujui berflag surat dokter per pegawai
     * (list [mulai, selesai] tanggal Y-m-d) dalam periode tertentu.
     */
    public static function sakitSuratRanges($pegawaiIds, $start, $end): Collection
    {
        return PengajuanIzin::whereIn('pegawai_id', $pegawaiIds)
            ->where('status', 'disetujui')
            ->where('jenis_izin', 'sakit')
            ->where('dengan_surat_dokter', true)
            ->whereDate('tanggal_mulai', '<=', $end->toDateString())
            ->whereDate('tanggal_selesai', '>=', $start->toDateString())
            ->get(['pegawai_id', 'tanggal_mulai', 'tanggal_selesai'])
            ->groupBy('pegawai_id')
            ->map(fn ($items) => $items->map(fn ($r) => [
                Carbon::parse($r->tanggal_mulai)->toDateString(),
                Carbon::parse($r->tanggal_selesai)->toDateString(),
            ]));
    }
}
