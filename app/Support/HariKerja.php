<?php

namespace App\Support;

use App\Constants\PresensiMessages;
use App\Models\HariLibur;
use App\Models\Pegawai;
use App\Models\UnitPresensiModeHistory;
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

    /**
     * Window lembur berdasarkan semua unit pegawai. Lembur hanya boleh mulai
     * setelah unit terakhir selesai; unit tutup dilewati dan jam tidak lengkap
     * memblokir karena batasnya tidak bisa ditentukan.
     *
     * @return array{tersedia: bool, batas_akhir: ?string, unit_terakhir: ?string, alasan: ?string}
     */
    public static function lemburWindow(Pegawai $pegawai, ?Carbon $tanggal = null): array
    {
        $tanggal ??= Carbon::now();
        $hari = self::hariIndo($tanggal);
        $units = $pegawai->relationLoaded('units') ? $pegawai->units : null;

        if ($units === null || $units->isEmpty()) {
            $units = $pegawai->units()->get();
        }

        if ($units->isEmpty()) {
            return [
                'tersedia' => false,
                'batas_akhir' => null,
                'unit_terakhir' => null,
                'alasan' => PresensiMessages::PEGAWAI_TIDAK_PUNYA_UNIT,
            ];
        }

        $latestEnd = null;
        $latestUnit = null;

        foreach ($units as $unit) {
            if (self::liburUnit($unit, $tanggal) || ! self::unitBukaHari($unit, $hari)) {
                continue;
            }

            $window = self::jamKerjaUnit($unit, $hari);
            if ($window === null) {
                return [
                    'tersedia' => false,
                    'batas_akhir' => null,
                    'unit_terakhir' => $unit->singkatan ?: $unit->nama,
                    'alasan' => PresensiMessages::LEMBUR_JAM_TIDAK_LENGKAP,
                ];
            }

            [$mulai, $selesai] = $window;
            if ($mulai === null || $selesai === null) {
                return [
                    'tersedia' => false,
                    'batas_akhir' => null,
                    'unit_terakhir' => $unit->singkatan ?: $unit->nama,
                    'alasan' => PresensiMessages::LEMBUR_JAM_TIDAK_LENGKAP,
                ];
            }

            if ($latestEnd === null || $selesai > $latestEnd) {
                $latestEnd = $selesai;
                $latestUnit = $unit->singkatan ?: $unit->nama;
            }
        }

        if ($latestEnd === null) {
            return [
                'tersedia' => true,
                'batas_akhir' => null,
                'unit_terakhir' => null,
                'alasan' => PresensiMessages::LEMBUR_UNIT_TUTUP,
            ];
        }

        $batas = substr($latestEnd, 0, 5);
        $tersedia = $tanggal->format('H:i:s') >= $latestEnd;

        return [
            'tersedia' => $tersedia,
            'batas_akhir' => $batas,
            'unit_terakhir' => $latestUnit,
            'alasan' => $tersedia
                ? 'Di luar jam kerja semua unit.'
                : sprintf(PresensiMessages::LEMBUR_BELUM_WAKTUNYA, $batas.' ('.$latestUnit.')'),
        ];
    }

    /**
     * Kuota mingguan hanya dihitung untuk tanggal setelah aturan unit berlaku.
     *
     * @return list<array{minggu: int, from: string, to: string, target: int, hari_kerja: int, partial: bool, segments: list<array<string, mixed>>}>
     */
    public static function mingguKerjaGuru(
        Pegawai $pegawai,
        UnitSekolah $unit,
        Carbon $start,
        Carbon $end,
        array $holidayMap
    ): array {
        $start = $start->copy()->startOfDay();
        $end = $end->copy()->startOfDay();
        if ($start->gt($end)) {
            return [];
        }

        $pegawai->loadMissing('jadwals');
        $scheduledDays = $pegawai->jadwals
            ->filter(fn ($jadwal) => $jadwal->jenis_jadwal === 'mengajar'
                && (int) $jadwal->unit_sekolah_id === (int) $unit->id)
            ->pluck('hari')
            ->unique()
            ->values()
            ->all();

        $isPendidik = $pegawai->isPendidik();
        $quotaAktif = $isPendidik && (bool) $unit->min_hari_berlaku_mulai;
        $minHari = (int) ($unit->min_hari_minggu ?? 0);
        $minDate = $unit->min_hari_berlaku_mulai?->copy()->startOfDay();
        $rows = [];
        // Minggu pertama dipotong ke tanggal awal; minggu berikutnya maju 7 hari
        // dari minggu sebelumnya (bukan startOfWeek, yang mundur ke Senin lalu
        // saat awal periode jatuh pada Minggu).
        $cursor = $start->copy()->startOfWeek(Carbon::MONDAY);
        if ($cursor->lt($start)) {
            $cursor = $start->copy();
        }
        $minggu = 1;

        while ($cursor->lte($end)) {
            $weekStart = $cursor->copy();
            $weekEnd = $weekStart->copy()->addDays(6);
            if ($weekEnd->gt($end)) {
                $weekEnd = $end->copy();
            }

            $segments = $isPendidik
                ? $unit->modeSegments($weekStart, $weekEnd)
                : [[
                    'from' => $weekStart->copy(),
                    'to' => $weekEnd->copy(),
                    'mode' => UnitPresensiModeHistory::MODE_KANTOR,
                ]];

            $weekTarget = 0;
            $weekHariKerja = 0;
            $detail = [];
            foreach ($segments as $segment) {
                $segmentStart = Carbon::parse($segment['from'])->startOfDay();
                $segmentEnd = Carbon::parse($segment['to'])->startOfDay();
                $mode = $segment['mode'];
                $scheduledDates = self::scheduledDates($segmentStart, $segmentEnd, $scheduledDays, $unit, $holidayMap);
                $workDates = self::workDates($segmentStart, $segmentEnd, $unit, $holidayMap);
                $targetDates = [];
                $target = 0;

                if ($mode === UnitPresensiModeHistory::MODE_HANYA_JADWAL && ! $quotaAktif) {
                    $hariKerja = count($scheduledDates);
                } elseif ($mode === UnitPresensiModeHistory::MODE_HANYA_JADWAL) {
                    $quotaStart = $minDate && $minDate->gt($segmentStart)
                        ? $minDate->copy()
                        : $segmentStart->copy();
                    if ($minHari > 0 && $minDate && $quotaStart->lte($segmentEnd)) {
                        $quotaFrom = $quotaStart->toDateString();
                        $targetDates = array_values(array_filter(
                            $workDates,
                            fn (string $date) => $date >= $quotaFrom
                        ));
                        $target = min($minHari, count($targetDates));
                    }

                    $hariKerja = max(count($scheduledDates), $target);
                } else {
                    $hariKerja = count($workDates);
                }

                $weekTarget += $target;
                $weekHariKerja += $hariKerja;
                $detail[] = [
                    'from' => $segmentStart->toDateString(),
                    'to' => $segmentEnd->toDateString(),
                    'mode' => $mode,
                    'target' => $target,
                    'hari_kerja' => $hariKerja,
                    'scheduled_dates' => $scheduledDates,
                    'quota_dates' => $targetDates,
                    'work_dates' => $workDates,
                ];
            }

            $minChanged = $minDate && $minDate->betweenIncluded($weekStart, $weekEnd);
            $rows[] = [
                'minggu' => $minggu,
                'from' => $weekStart->toDateString(),
                'to' => $weekEnd->toDateString(),
                'target' => $weekTarget,
                'hari_kerja' => $weekHariKerja,
                'partial' => count($segments) > 1
                    || $weekStart->gt($start)
                    || $weekEnd->lt($end)
                    || (bool) $minChanged,
                'segments' => $detail,
            ];

            $cursor = $weekEnd->copy()->addDay()->startOfDay();
            $minggu++;
        }

        return $rows;
    }

    /**
     * Total hari kerja dalam rentang.
     *
     * $withQuota = true memakai kuota mingguan: minimal kuota tetap dihitung
     * bila pegawai punya jadwal di unit (dasar hitung prorata & laporan);
     * tanpa jadwal hari itu bukan hari kerja. $withQuota = false memakai
     * jumlah hari kalender terjadwal/unit apa adanya (semantik payroll HEAD).
     */
    public static function hariKerjaGuru(
        Pegawai $pegawai,
        UnitSekolah $unit,
        Carbon $start,
        Carbon $end,
        array $holidayMap,
        bool $withQuota = true
    ): int {
        $rows = self::mingguKerjaGuru($pegawai, $unit, $start, $end, $holidayMap);
        $total = 0;
        foreach ($rows as $row) {
            foreach ($row['segments'] as $segment) {
                $semua = $segment['mode'] === UnitPresensiModeHistory::MODE_HANYA_JADWAL
                    ? count($segment['scheduled_dates'])
                    : count($segment['work_dates']);

                if (! $withQuota) {
                    $total += $semua;

                    continue;
                }

                $total += $semua > 0 ? (int) $segment['hari_kerja'] : 0;
            }
        }

        return $total;
    }

    /**
     * @param  list<string>  $scheduledDays
     * @return list<string>
     */
    private static function scheduledDates(
        Carbon $start,
        Carbon $end,
        array $scheduledDays,
        UnitSekolah $unit,
        array $holidayMap
    ): array {
        $scheduled = array_flip($scheduledDays);
        $dates = [];
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $day = self::hariIndo($date);
            if (! isset($scheduled[$day]) || self::isHoliday($date, $unit, $holidayMap)) {
                continue;
            }
            $dates[] = $date->toDateString();
        }

        return $dates;
    }

    /**
     * @return list<string>
     */
    private static function workDates(
        Carbon $start,
        Carbon $end,
        UnitSekolah $unit,
        array $holidayMap
    ): array {
        $workingDays = array_flip(Pegawai::hariKerjaUnit($unit));
        $dates = [];
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $day = self::hariIndo($date);
            if (! isset($workingDays[$day]) || self::isHoliday($date, $unit, $holidayMap)) {
                continue;
            }
            $dates[] = $date->toDateString();
        }

        return $dates;
    }

    private static function isHoliday(Carbon $date, UnitSekolah $unit, array $holidayMap): bool
    {
        $key = $date->toDateString();
        $unitDates = $holidayMap['unit_dates'][$unit->id]
            ?? $holidayMap['unit_dates'][(string) $unit->id]
            ?? [];

        return self::hasDate($holidayMap['national_dates'] ?? [], $key)
            || self::hasDate($unitDates, $key);
    }

    /**
     * Holiday map bisa berupa map tanggal => nama hari (dari buildHolidayWeekdayMap)
     * atau list tanggal (dari pemanggil lain) — dua-duanya harus dikenali.
     */
    private static function hasDate(array $dates, string $key): bool
    {
        return in_array($key, $dates, true) || array_key_exists($key, $dates);
    }

    private static function jamKerjaUnit(UnitSekolah $unit, string $hari): ?array
    {
        $mulai = match ($hari) {
            'Sabtu' => $unit->jam_kerja_sabtu_mulai,
            'Minggu' => $unit->jam_kerja_minggu_mulai,
            default => $unit->jam_masuk_kantor,
        };
        $selesai = match ($hari) {
            'Sabtu' => $unit->jam_kerja_sabtu_selesai,
            'Minggu' => $unit->jam_kerja_minggu_selesai,
            default => $unit->jam_pulang_kantor,
        };

        if (! $mulai || ! $selesai || $selesai <= $mulai) {
            return null;
        }

        return [substr((string) $mulai, 0, 8), substr((string) $selesai, 0, 8)];
    }

    private static function liburUnit(UnitSekolah $unit, Carbon $tanggal): bool
    {
        return HariLibur::query()
            ->whereDate('tanggal', $tanggal->toDateString())
            ->where(function ($query) use ($unit) {
                $query->whereNull('unit_sekolah_id')
                    ->orWhere('unit_sekolah_id', $unit->id);
            })
            ->exists();
    }
}
