<?php

namespace App\Services;

use App\Models\HariLibur;
use App\Models\Jadwal;
use App\Models\LaporanPiket;
use App\Models\Pegawai;
use App\Models\PengajuanIzin;
use App\Models\PiketTugasOverride;
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
        $piketTugasMap = $this->piketTugasMap($pegawai, $unit, $start, $end);
        $tugasOverrides = PiketTugasOverride::query()
            ->where('pegawai_id', $pegawai->id)
            ->whereDate('tanggal', '>=', $start->toDateString())
            ->whereDate('tanggal', '<=', $end->toDateString())
            ->get()
            ->keyBy(fn (PiketTugasOverride $override) => $override->tanggal->toDateString());
        $bobot = $unit->bobotHadiran();
        $sakitWajibTugas = $unit->sakitWajibTugas();
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

            $code = $this->keterangan($dayRows, $date, $today->toDateString(), $izinMap);
            $actualJp = $actualIds->count();
            $scheduledJp = $scheduledIds->count();

            $piketTugas = $piketTugasMap[$date] ?? null;
            $override = $tugasOverrides->get($date);
            $tugas = $override
                ? (bool) $override->ada_amanat
                : (bool) ($piketTugas['ada_amanat'] ?? false);
            $tugasTersampaikan = $override
                ? (bool) $override->tersampaikan
                : (bool) ($piketTugas['tersampaikan'] ?? false);
            $tugasSumber = $override ? 'operator' : ($piketTugas ? 'piket' : null);
            $tugasLuar = $dayRows->contains(
                fn (Presensi $row) => $row->is_tugas_luar && $row->tugas_luar_status === 'disetujui'
            );

            $attendanceWeight = $this->attendanceWeight(
                $code,
                $dayRows,
                $izinMap[$date] ?? null,
                $bobot,
                $sakitWajibTugas,
                $tugas,
            );
            $ph = $attendanceWeight === null ? null : (int) round($attendanceWeight * 100);

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
                'attendance_weight' => $attendanceWeight,
                'ph' => $ph,
                's' => $code === 'S',
                'i' => $code === 'I',
                'tk' => $code === 'TK',
                'tugas' => $tugas,
                'tugas_luar' => $tugasLuar,
                'tugas_tersampaikan' => $tugasTersampaikan,
                'tugas_sumber' => $tugasSumber,
                'keterangan' => $code,
            ];
        }

        $weeks = $this->weeks($rows);
        $actualTotal = array_sum(array_column($rows, 'jumlah_jp'));
        $scheduledTotal = array_sum(array_column($rows, 'scheduled_jp'));
        $attendanceWeights = collect($rows)
            ->map(fn (array $row) => (float) ($row['attendance_weight'] ?? 0));

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
                'b' => $attendanceWeights->isNotEmpty()
                    ? (int) round($attendanceWeights->sum() * 100 / $attendanceWeights->count())
                    : null,
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
            $weights = $weekRows->map(fn (array $row) => (float) ($row['attendance_weight'] ?? 0));

            $weeks[] = [
                'label' => 'Minggu '.$number++,
                'rows' => $weekRows->values()->all(),
                'm' => $weights->isNotEmpty()
                    ? (int) round($weights->sum() * 100 / $weights->count())
                    : null,
                'actual_jp' => $actual,
                'scheduled_jp' => $scheduled,
            ];
        }

        return $weeks;
    }

    private function keterangan(Collection $rows, string $date, string $today, array $izinMap): ?string
    {
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
            return $izin['jenis_izin'] === 'sakit' ? 'S' : 'I';
        }

        return $date < $today ? 'TK' : null;
    }

    private function piketTugasMap(Pegawai $pegawai, UnitSekolah $unit, Carbon $start, Carbon $end): array
    {
        $map = [];

        LaporanPiket::query()
            ->where('unit_sekolah_id', $unit->id)
            ->where('status', LaporanPiket::STATUS_SUBMITTED)
            ->whereDate('tanggal', '>=', $start->toDateString())
            ->whereDate('tanggal', '<=', $end->toDateString())
            ->get(['tanggal', 'items'])
            ->each(function (LaporanPiket $laporan) use (&$map, $pegawai) {
                $date = $laporan->tanggal->toDateString();
                $entries = $laporan->items[LaporanPiket::ITEM_KELAS_KOSONG]['entries'] ?? [];

                foreach ($entries as $entry) {
                    if ((int) ($entry['guru_pegawai_id'] ?? 0) !== (int) $pegawai->id) {
                        continue;
                    }

                    $adaAmanat = (bool) ($entry['ada_amanat'] ?? false);
                    if (! isset($map[$date]) || ($adaAmanat && ! $map[$date]['ada_amanat'])) {
                        $map[$date] = [
                            'ada_amanat' => $adaAmanat,
                            'tersampaikan' => (bool) ($entry['tersampaikan'] ?? false),
                            'catatan' => $entry['detail_amanat'] ?? null,
                        ];
                    }
                }
            });

        return $map;
    }

    /**
     * Bobot kehadiran harian dari status + aturan unit.
     * Null berarti hari belum selesai (hari ini tanpa presensi).
     */
    private function attendanceWeight(
        ?string $code,
        Collection $rows,
        ?array $leave,
        array $bobot,
        bool $sakitWajibTugas,
        bool $tugas,
    ): ?float {
        if ($rows->contains(fn (Presensi $row) => $row->is_tugas_luar && $row->tugas_luar_status === 'disetujui')) {
            return 1.0;
        }

        if ($code === null) {
            return $rows->contains(fn (Presensi $row) => in_array($row->status, ['hadir', 'telat'], true)) ? 1.0 : null;
        }

        if ($code === 'TUGAS') {
            return 1.0;
        }

        if ($code === 'TK') {
            return 0.0;
        }

        if ($code === 'S') {
            $punyaTugas = $tugas || ! empty($leave['tugas_terverifikasi'] ?? null);
            $lengkap = ($sakitWajibTugas && $punyaTugas)
                || ($leave && ! empty($leave['dengan_surat_dokter']));

            return $lengkap ? (float) $bobot['sakit_surat'] : (float) $bobot['sakit_tanpa_surat'];
        }

        if ($code === 'I') {
            if ($rows->contains('status', 'cuti') || ($leave['jenis_izin'] ?? null) === 'cuti') {
                return (float) $bobot['cuti'];
            }

            if (! empty($leave['dihitung_setengah'])) {
                return (float) $bobot['izin_setengah'];
            }

            return (float) $bobot['izin'];
        }

        return null;
    }

    private function izinMap(Pegawai $pegawai, Carbon $start, Carbon $end): array
    {
        $map = [];

        PengajuanIzin::query()
            ->where('pegawai_id', $pegawai->id)
            ->where('status', 'disetujui')
            ->whereDate('tanggal_mulai', '<=', $end->toDateString())
            ->whereDate('tanggal_selesai', '>=', $start->toDateString())
            ->get([
                'jenis_izin',
                'tanggal_mulai',
                'tanggal_selesai',
                'dengan_surat_dokter',
                'dihitung_setengah',
                'tugas_terverifikasi',
            ])
            ->each(function (PengajuanIzin $izin) use (&$map) {
                $cursor = Carbon::parse($izin->tanggal_mulai);
                $end = Carbon::parse($izin->tanggal_selesai);

                while ($cursor->lte($end)) {
                    $map[$cursor->toDateString()] = [
                        'jenis_izin' => $izin->jenis_izin,
                        'dengan_surat_dokter' => (bool) $izin->dengan_surat_dokter,
                        'dihitung_setengah' => (bool) $izin->dihitung_setengah,
                        'tugas_terverifikasi' => (bool) $izin->tugas_terverifikasi,
                    ];
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
