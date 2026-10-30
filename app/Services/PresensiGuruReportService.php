<?php

namespace App\Services;

use App\Models\HariLibur;
use App\Models\Jadwal;
use App\Models\Pegawai;
use App\Models\PengajuanIzin;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PresensiGuruReportService
{
    private const STATUS_PRIORITY = ['hadir' => 0, 'telat' => 1, 'sakit' => 2, 'izin' => 3, 'cuti' => 4, 'alpa' => 5];

    public function build(Pegawai $pegawai, UnitSekolah $unit, string $periode, string $tahunAjaran, int $semester): array
    {
        $start = Carbon::createFromFormat('Y-m', $periode)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $jadwals = Jadwal::query()
            ->where('pegawai_id', $pegawai->id)
            ->where('unit_sekolah_id', $unit->id)
            ->where('jenis_jadwal', 'mengajar')
            ->where('tahun_ajaran', $tahunAjaran)
            ->where('semester', $semester)
            ->get();

        $slotTimes = $jadwals->pluck('jam_mulai')
            ->map(fn ($time) => substr((string) $time, 0, 5))
            ->unique()
            ->sort()
            ->values();

        $slotCount = max(11, $slotTimes->count());
        $slotIndex = $slotTimes->flip()->all();

        $presensis = Presensi::with('jadwal')
            ->where('pegawai_id', $pegawai->id)
            ->whereBetween('tanggal', [$start->toDateString(), $end->toDateString()])
            ->where('is_lembur', false)
            ->get()
            ->groupBy(fn (Presensi $row) => Carbon::parse($row->tanggal)->toDateString());

        $izinMap = $this->izinMap($pegawai, $start, $end);
        $holidays = HariLibur::forUnit($unit->id)
            ->whereBetween('tanggal', [$start->toDateString(), $end->toDateString()])
            ->pluck('tanggal')
            ->map(fn ($tanggal) => Carbon::parse($tanggal)->toDateString())
            ->all();

        $workingDays = Pegawai::hariKerjaUnit($unit);
        $today = Carbon::today();
        $rows = [];

        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
            if ($cursor->gt($today)) {
                continue;
            }

            $date = $cursor->toDateString();
            $dayName = $cursor->locale('id')->translatedFormat('l');

            if (! in_array($dayName, $workingDays, true) || in_array($date, $holidays, true)) {
                continue;
            }

            $dayRows = $presensis->get($date, collect());
            $scheduled = $jadwals->where('hari', $dayName);
            $scheduledIds = $scheduled->pluck('id')->unique()->values();
            $actual = $dayRows->filter(fn (Presensi $row) => $row->jadwal_id
                && in_array($row->status, ['hadir', 'telat'], true));
            $actualIds = $actual->pluck('jadwal_id')->unique()->values();

            $code = $this->keterangan($dayRows, $scheduledIds->isNotEmpty(), $date, $today->toDateString(), $izinMap);
            $actualJp = $actualIds->count();
            $scheduledJp = $scheduledIds->count();
            $ph = null;
            if ($scheduledJp > 0 && ! ($code && $actualJp === 0)) {
                $ph = (int) round(($actualJp / $scheduledJp) * 100);
            }

            $slots = [];
            foreach ($actual->unique('jadwal_id') as $presensi) {
                $time = substr((string) $presensi->jadwal?->jam_mulai, 0, 5);
                if (isset($slotIndex[$time])) {
                    $slots[$slotIndex[$time] + 1] = true;
                }
            }

            $rows[] = [
                'tanggal' => $cursor->format('d/m/Y'),
                'tanggal_iso' => $date,
                'hari' => $cursor->locale('id')->translatedFormat('l'),
                'jam_datang' => $this->earliest($dayRows, 'jam_masuk'),
                'jam_pulang' => $this->latest($dayRows, 'jam_keluar'),
                'slots' => $slots,
                'jumlah_jp' => $actualJp,
                'scheduled_jp' => $scheduledJp,
                'ph' => $ph,
                's' => $code === 'S',
                'i' => $code === 'I',
                'tk' => $code === 'TK',
                'tugas' => $code === 'TUGAS',
                'keterangan' => $code,
            ];
        }

        $weeks = $this->weeks($rows);
        $actualTotal = array_sum(array_column($rows, 'jumlah_jp'));
        $scheduledTotal = array_sum(array_column($rows, 'scheduled_jp'));

        return [
            'unit' => [
                'nama' => $unit->nama,
                'singkatan' => $unit->singkatan,
                'logo' => $unit->logo,
                'logo_url' => $unit->logo_url,
                'alamat' => $unit->alamat,
                'telepon' => $unit->telepon,
                'web' => $unit->web,
            ],
            'pegawai' => [
                'id' => $pegawai->id,
                'nama' => $pegawai->nama_lengkap,
                'nip' => $pegawai->nip,
                'nuptk' => $pegawai->nuptk,
            ],
            'periode' => $periode,
            'periode_label' => $start->locale('id')->translatedFormat('F Y'),
            'tahun_ajaran' => $tahunAjaran,
            'semester' => $semester,
            'slot_count' => $slotCount,
            'rows' => $rows,
            'weeks' => $weeks,
            'summary' => [
                'actual_jp' => $actualTotal,
                'scheduled_jp' => $scheduledTotal,
                'b' => $this->percentage($actualTotal, $scheduledTotal),
            ],
            'officials' => $this->officials($unit, $pegawai),
        ];
    }

    private function weeks(array $rows): array
    {
        $groups = collect($rows)
            ->groupBy(fn (array $row) => Carbon::parse($row['tanggal_iso'])->startOfWeek(Carbon::MONDAY)->toDateString())
            ->sortKeys();

        $weeks = [];
        $number = 1;

        foreach ($groups as $weekRows) {
            $actual = array_sum(array_column($weekRows->all(), 'jumlah_jp'));
            $scheduled = array_sum(array_column($weekRows->all(), 'scheduled_jp'));

            $weeks[] = [
                'label' => 'Minggu '.$number++,
                'rows' => $weekRows->values()->all(),
                'm' => $this->percentage($actual, $scheduled),
                'actual_jp' => $actual,
                'scheduled_jp' => $scheduled,
            ];
        }

        return $weeks;
    }

    private function percentage(int $actual, int $scheduled): ?int
    {
        return $scheduled > 0 ? (int) round(($actual / $scheduled) * 100) : null;
    }

    private function keterangan(Collection $rows, bool $hasSchedule, string $date, string $today, array $izinMap): ?string
    {
        if ($rows->contains(fn (Presensi $row) => $row->is_tugas_luar && $row->tugas_luar_status === 'disetujui')) {
            return 'TUGAS';
        }

        $row = $rows->sortBy(fn (Presensi $row) => self::STATUS_PRIORITY[$row->status] ?? 9)->first();
        if ($row) {
            return match ($row->status) {
                'sakit' => 'S',
                'izin', 'cuti' => 'I',
                'alpa' => 'TK',
                default => null,
            };
        }

        $izin = $izinMap[$date] ?? null;
        if ($izin) {
            return $izin === 'sakit' ? 'S' : 'I';
        }

        if ($hasSchedule && $date < $today) {
            return 'TK';
        }

        return null;
    }

    private function izinMap(Pegawai $pegawai, Carbon $start, Carbon $end): array
    {
        $map = [];

        PengajuanIzin::query()
            ->where('pegawai_id', $pegawai->id)
            ->where('status', 'disetujui')
            ->where('tanggal_mulai', '<=', $end->toDateString())
            ->where('tanggal_selesai', '>=', $start->toDateString())
            ->get(['jenis_izin', 'tanggal_mulai', 'tanggal_selesai'])
            ->each(function (PengajuanIzin $izin) use (&$map) {
                $cursor = Carbon::parse($izin->tanggal_mulai);
                $end = Carbon::parse($izin->tanggal_selesai);

                while ($cursor->lte($end)) {
                    $map[$cursor->toDateString()] = $izin->jenis_izin;
                    $cursor->addDay();
                }
            });

        return $map;
    }

    private function earliest(Collection $rows, string $column): ?string
    {
        $value = $rows->pluck($column)->filter()->sort()->first();

        return $value ? substr((string) $value, 0, 5) : null;
    }

    private function latest(Collection $rows, string $column): ?string
    {
        $value = $rows->pluck($column)->filter()->sortDesc()->first();

        return $value ? substr((string) $value, 0, 5) : null;
    }

    private function officials(UnitSekolah $unit, Pegawai $pegawai): array
    {
        $kepala = Pegawai::whereHas('jabatans', fn ($q) => $q->where('nama', 'Kepala Sekolah'))
            ->whereHas('units', fn ($q) => $q->where('unit_sekolah.id', $unit->id))
            ->first();

        $kepalaTu = Pegawai::whereHas('jabatans', fn ($q) => $q->whereIn('nama', ['Kepala Tata Usaha', 'Kepala TU']))
            ->whereHas('units', fn ($q) => $q->where('unit_sekolah.id', $unit->id))
            ->first();

        $kepegawaian = User::whereHas('roles', fn ($q) => $q->where('name', 'tu_kepegawaian'))
            ->where('unit_sekolah_id', $unit->id)
            ->first();

        return [
            'kepala' => $kepala?->nama_lengkap,
            'kepala_tu' => $kepalaTu?->nama_lengkap,
            'kepegawaian' => $kepegawaian?->name,
            'guru' => $pegawai->nama_lengkap,
        ];
    }
}
