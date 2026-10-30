<?php

namespace App\Services;

use App\Http\Controllers\Concerns\ScopesPimpinan;
use App\Models\HariLibur;
use App\Models\Jadwal;
use App\Models\LaporanPiket;
use App\Models\Pegawai;
use App\Models\PengajuanIzin;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class LaporanPengawasanService
{
    use ScopesPimpinan;

    private const STATUS_PRIORITY = [
        'hadir' => 0,
        'telat' => 1,
        'sakit' => 2,
        'izin' => 3,
        'cuti' => 4,
        'alpa' => 5,
    ];

    public function build(array $filters, ?User $user = null): array
    {
        $user ??= auth()->user();
        $from = Carbon::parse($filters['from'])->startOfDay();
        $to = Carbon::parse($filters['to'])->endOfDay();
        $selectedUnitId = isset($filters['unit_sekolah_id']) && $filters['unit_sekolah_id'] !== null
            ? (int) $filters['unit_sekolah_id']
            : null;
        $jenisPegawai = $filters['jenis_pegawai'] ?? 'all';
        $tahunAjaran = $filters['tahun_ajaran'] ?? $this->defaultTahunAjaran($from);
        $semester = (int) ($filters['semester'] ?? ($from->month >= 7 ? 1 : 2));

        $units = $this->visibleUnits($user, $selectedUnitId);
        abort_if($units->isEmpty(), 403, 'Tidak ada unit yang dapat diakses.');

        $unitIds = $units->pluck('id')->all();
        $employees = $this->employees($user, $unitIds, $jenisPegawai);

        $employeeRows = $employees->map(function (Pegawai $pegawai) use ($units, $selectedUnitId) {
            $unit = $this->employeeUnit($pegawai, $units, $selectedUnitId);

            return $unit ? ['pegawai' => $pegawai, 'unit' => $unit] : null;
        })->filter()->values();

        $employeeIds = $employeeRows->pluck('pegawai.id')->all();
        $holidayMap = $this->holidayMap($unitIds, $from, $to);
        $workdaysByUnit = $this->workdaysByUnit($units, $from, $to, $holidayMap);
        $leaveMap = $this->leaveMap($employeeIds, $from, $to);
        $presensiMap = $this->presensiMap($employeeIds, $from, $to);
        $schedules = $this->schedules($employeeIds, $unitIds, $tahunAjaran, $semester);
        $schedulesByEmployeeDay = $schedules->groupBy(fn (Jadwal $jadwal) => $jadwal->pegawai_id.'|'.$jadwal->hari);
        $actualBySlot = $this->actualBySlot($presensiMap);
        $piket = $this->piketEntries($unitIds, $from, $to);

        $alpaRows = [];
        $hadirTanpaJpRows = [];
        $jpKurangRows = [];
        $izinMengajarRows = [];
        $classIssues = [];
        $kelasBelumRows = [];
        $matrix = [];
        $unitSummary = $units->mapWithKeys(fn (UnitSekolah $unit) => [
            $unit->id => [
                'unit_sekolah_id' => $unit->id,
                'nama' => $unit->nama,
                'singkatan' => $unit->singkatan,
                'employees' => 0,
                'employee_days' => 0,
                'hadir' => 0,
                'telat' => 0,
                'izin' => 0,
                'sakit' => 0,
                'cuti' => 0,
                'alpa' => 0,
                'tugas' => 0,
                'actual_jp' => 0,
                'scheduled_jp' => 0,
            ],
        ])->all();

        foreach ($employeeRows as $row) {
            /** @var Pegawai $pegawai */
            $pegawai = $row['pegawai'];
            /** @var UnitSekolah $unit */
            $unit = $row['unit'];
            $isGuru = $pegawai->jabatans->contains(fn ($jabatan) => (bool) $jabatan->is_guru);
            $workdays = $workdaysByUnit[$unit->id] ?? [];
            $stats = [
                'employee_days' => 0,
                'hadir' => 0,
                'telat' => 0,
                'izin' => 0,
                'sakit' => 0,
                'cuti' => 0,
                'alpa' => 0,
                'tugas' => 0,
                'actual_jp' => 0,
                'scheduled_jp' => 0,
                'hadir_tanpa_jp' => 0,
                'jp_kurang' => 0,
                'izin_mengajar' => 0,
            ];

            foreach ($workdays as $date) {
                $dayName = Carbon::parse($date)->locale('id')->translatedFormat('l');
                $rows = $presensiMap[$pegawai->id.'|'.$date] ?? collect();
                $leave = $leaveMap[$pegawai->id][$date] ?? null;
                $status = $this->dayStatus($rows, $leave);
                $scheduledSlots = $schedulesByEmployeeDay->get($pegawai->id.'|'.$dayName, collect());
                $scheduledJp = round($scheduledSlots->sum(fn (Jadwal $slot) => JpCalculator::weightFromJadwal($slot)), 2);
                $actualJadwalIds = $rows
                    ->filter(fn (Presensi $presensi) => $presensi->jadwal_id && in_array($presensi->status, ['hadir', 'telat'], true))
                    ->pluck('jadwal_id')
                    ->unique();
                $actualJp = round($scheduledSlots
                    ->filter(fn (Jadwal $slot) => $actualJadwalIds->contains($slot->id))
                    ->sum(fn (Jadwal $slot) => JpCalculator::weightFromJadwal($slot)), 2);
                $actualPresence = in_array($status, ['hadir', 'telat'], true);

                $stats['employee_days']++;
                $stats[$status] = ($stats[$status] ?? 0) + 1;
                $stats['actual_jp'] += $actualJp;
                $stats['scheduled_jp'] += $scheduledJp;

                if ($status === 'alpa') {
                    $alpaRows[] = [
                        'pegawai_id' => $pegawai->id,
                        'pegawai' => $pegawai->nama_lengkap,
                        'unit' => $unit->nama,
                        'tanggal' => $date,
                        'sumber' => 'Tidak ada presensi dan tidak ada izin',
                    ];
                }

                if ($leave && $actualPresence) {
                    $stats['izin_mengajar']++;
                    $izinMengajarRows[] = [
                        'pegawai_id' => $pegawai->id,
                        'pegawai' => $pegawai->nama_lengkap,
                        'unit' => $unit->nama,
                        'tanggal' => $date,
                        'izin' => $leave,
                        'realisasi' => $this->formatJp($actualJp).' JP',
                    ];
                }

                if ($isGuru && $actualPresence && $scheduledJp > 0 && $actualJp <= 0) {
                    $stats['hadir_tanpa_jp']++;
                    $hadirTanpaJpRows[] = [
                        'pegawai_id' => $pegawai->id,
                        'pegawai' => $pegawai->nama_lengkap,
                        'unit' => $unit->nama,
                        'tanggal' => $date,
                        'scheduled_jp' => $scheduledJp,
                        'actual_jp' => 0,
                    ];
                }

                if ($isGuru && $actualJp > 0 && $actualJp < $scheduledJp) {
                    $stats['jp_kurang']++;
                    $jpKurangRows[] = [
                        'pegawai_id' => $pegawai->id,
                        'pegawai' => $pegawai->nama_lengkap,
                        'unit' => $unit->nama,
                        'tanggal' => $date,
                        'scheduled_jp' => $scheduledJp,
                        'actual_jp' => $actualJp,
                        'kurang' => round($scheduledJp - $actualJp, 2),
                    ];
                }

                foreach ($scheduledSlots as $slot) {
                    $actual = $actualBySlot[$date.'|'.$slot->id] ?? null;
                    $piketEntry = $this->piketForSlot($piket, $date, $unit->id, $slot);
                    $classStatus = 'belum_terpantau';
                    $source = 'Jadwal + Presensi';

                    if ($actual && $piketEntry) {
                        $classStatus = 'perlu_cek';
                        $source = 'Presensi + Piket';
                    } elseif ($actual) {
                        $classStatus = (int) $actual->pegawai_id !== (int) $slot->pegawai_id ? 'digantikan' : 'terisi';
                        $source = 'Presensi';
                    } elseif ($piketEntry) {
                        $classStatus = ($piketEntry['validasi'] ?? null) === 'perlu_cek' ? 'perlu_cek' : 'kelas_kosong';
                        $source = 'Piket';
                    }

                    if ($classStatus === 'terisi') {
                        continue;
                    }

                    $issue = [
                        'tanggal' => $date,
                        'jam' => substr((string) $slot->jam_mulai, 0, 5).'-'.substr((string) $slot->jam_selesai, 0, 5),
                        'kelas' => $slot->kelas_label,
                        'mapel' => $slot->pegawaiMapel?->mataPelajaran?->nama,
                        'guru' => $slot->pegawai?->nama_lengkap,
                        'actual_guru' => $actual?->pegawai?->nama_lengkap,
                        'unit' => $unit->nama,
                        'status' => $classStatus,
                        'sumber' => $source,
                        'catatan' => $piketEntry['detail_amanat'] ?? $piketEntry['validasi_catatan'] ?? null,
                    ];
                    $classIssues[] = $issue;

                    if ($classStatus === 'belum_terpantau') {
                        $kelasBelumRows[] = $issue;
                    }
                }
            }

            $findings = [];
            if ($stats['alpa'] > 0) {
                $findings[] = $stats['alpa'].' hari alpa';
            }
            if ($stats['hadir_tanpa_jp'] > 0) {
                $findings[] = $stats['hadir_tanpa_jp'].' hari hadir tanpa JP';
            }
            if ($stats['jp_kurang'] > 0) {
                $findings[] = $stats['jp_kurang'].' hari JP kurang';
            }
            if ($stats['izin_mengajar'] > 0) {
                $findings[] = $stats['izin_mengajar'].' hari izin tetapi mengajar';
            }

            $matrix[] = [
                'pegawai_id' => $pegawai->id,
                'pegawai' => $pegawai->nama_lengkap,
                'unit' => $unit->nama,
                'unit_id' => $unit->id,
                'is_guru' => $isGuru,
                ...$stats,
                'temuan' => $findings ? implode(' · ', $findings) : 'Sesuai',
            ];

            if (isset($unitSummary[$unit->id])) {
                foreach (['employee_days', 'hadir', 'telat', 'izin', 'sakit', 'cuti', 'alpa', 'tugas', 'actual_jp', 'scheduled_jp'] as $key) {
                    $unitSummary[$unit->id][$key] += $stats[$key];
                }
                $unitSummary[$unit->id]['employees']++;
            }
        }

        usort($matrix, function (array $a, array $b) {
            $aScore = $a['alpa'] + $a['hadir_tanpa_jp'] + $a['jp_kurang'] + $a['izin_mengajar'];
            $bScore = $b['alpa'] + $b['hadir_tanpa_jp'] + $b['jp_kurang'] + $b['izin_mengajar'];

            return $bScore <=> $aScore ?: strcmp($a['pegawai'], $b['pegawai']);
        });

        $summary = [
            'employees' => count($matrix),
            'employee_days' => array_sum(array_column($matrix, 'employee_days')),
            'hadir' => array_sum(array_column($matrix, 'hadir')),
            'telat' => array_sum(array_column($matrix, 'telat')),
            'izin' => array_sum(array_column($matrix, 'izin')),
            'sakit' => array_sum(array_column($matrix, 'sakit')),
            'cuti' => array_sum(array_column($matrix, 'cuti')),
            'alpa' => array_sum(array_column($matrix, 'alpa')),
            'tugas' => array_sum(array_column($matrix, 'tugas')),
            'actual_jp' => array_sum(array_column($matrix, 'actual_jp')),
            'scheduled_jp' => array_sum(array_column($matrix, 'scheduled_jp')),
            'hadir_tanpa_jp' => count($hadirTanpaJpRows),
            'jp_kurang' => count($jpKurangRows),
            'izin_mengajar' => count($izinMengajarRows),
            'kelas_belum' => count($kelasBelumRows),
        ];
        $summary['izin_sakit_cuti'] = $summary['izin'] + $summary['sakit'] + $summary['cuti'];
        $summary['jp_percent'] = $summary['scheduled_jp'] > 0
            ? (int) round($summary['actual_jp'] * 100 / $summary['scheduled_jp'])
            : null;
        $summary['hadir_percent'] = $summary['employee_days'] > 0
            ? (int) round($summary['hadir'] * 100 / $summary['employee_days'])
            : null;

        $unitSummary = array_values(array_filter($unitSummary, fn (array $unit) => $unit['employees'] > 0));

        return [
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'label' => $from->isSameDay($to)
                    ? $from->locale('id')->translatedFormat('l, d F Y')
                    : $from->locale('id')->translatedFormat('d F Y').' - '.$to->locale('id')->translatedFormat('d F Y'),
                'days' => (int) $from->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1,
                'tahun_ajaran' => $tahunAjaran,
                'semester' => $semester,
            ],
            'unit' => $units->count() === 1 ? [
                'id' => $units->first()->id,
                'nama' => $units->first()->nama,
                'singkatan' => $units->first()->singkatan,
            ] : null,
            'summary' => $summary,
            'matrix' => $matrix,
            'units' => $unitSummary,
            'class_issues' => $classIssues,
            'details' => [
                'alpa' => $alpaRows,
                'hadir_tanpa_jp' => $hadirTanpaJpRows,
                'jp_kurang' => $jpKurangRows,
                'izin_mengajar' => $izinMengajarRows,
                'kelas_belum' => $kelasBelumRows,
            ],
            'generated_at' => now()->toDateTimeString(),
        ];
    }

    private function visibleUnits(?User $user, ?int $selectedUnitId): Collection
    {
        $query = UnitSekolah::query()->orderBy('nama');

        if ($selectedUnitId) {
            $query->whereKey($selectedUnitId);
        } elseif ($user && ! $user->can('view_all_units')) {
            if ($user->unit_sekolah_id) {
                $query->whereKey($user->unit_sekolah_id);
            } else {
                $query->whereIn('id', $user->pegawai?->units->pluck('id') ?? []);
            }
        }

        $units = $query->get();

        if ($selectedUnitId && $user && ! $user->can('view_all_units')) {
            $allowed = $user->unit_sekolah_id
                ? (int) $user->unit_sekolah_id === $selectedUnitId
                : $user->pegawai?->units->contains('id', $selectedUnitId) ?? false;
            abort_unless($allowed, 403, 'Unit tidak sesuai dengan akses Anda.');
        }

        return $units;
    }

    private function employees(?User $user, array $unitIds, string $jenisPegawai): Collection
    {
        $query = Pegawai::query()
            ->where('status_aktif', 'aktif')
            ->whereHas('units', fn ($q) => $q->whereIn('unit_sekolah.id', $unitIds))
            ->with(['units:id,nama,singkatan', 'jabatans:id,nama,is_guru'])
            ->orderBy('nama_lengkap')
            ->select(['id', 'nama_lengkap', 'atasan_langsung_id']);

        if ($jenisPegawai === 'guru') {
            $query->whereHas('jabatans', fn ($q) => $q->where('is_guru', true));
        } elseif ($jenisPegawai === 'non_guru') {
            $query->whereDoesntHave('jabatans', fn ($q) => $q->where('is_guru', true));
        }

        if ($user && $this->isPimpinanReadOnly($user) && ! $this->isKepsek($user)) {
            $atasanId = $user->pegawai?->id;
            if ($atasanId) {
                $query->where('atasan_langsung_id', $atasanId);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        return $query->get();
    }

    private function employeeUnit(Pegawai $pegawai, Collection $units, ?int $selectedUnitId): ?UnitSekolah
    {
        if ($selectedUnitId) {
            return $units->firstWhere('id', $selectedUnitId);
        }

        return $pegawai->units
            ->filter(fn (UnitSekolah $unit) => $units->contains('id', $unit->id))
            ->sortByDesc(fn (UnitSekolah $unit) => (int) ($unit->pivot->is_primary ?? 0))
            ->first();
    }

    private function holidayMap(array $unitIds, Carbon $from, Carbon $to): array
    {
        $rows = HariLibur::query()
            ->whereBetween('tanggal', [$from->toDateString(), $to->toDateString()])
            ->where(function ($query) use ($unitIds) {
                $query->whereNull('unit_sekolah_id')->orWhereIn('unit_sekolah_id', $unitIds);
            })
            ->get(['tanggal', 'unit_sekolah_id']);

        $map = ['national' => [], 'units' => []];

        foreach ($rows as $row) {
            $date = Carbon::parse($row->tanggal)->toDateString();
            if ($row->unit_sekolah_id === null) {
                $map['national'][$date] = true;
            } else {
                $map['units'][$row->unit_sekolah_id][$date] = true;
            }
        }

        return $map;
    }

    private function workdaysByUnit(Collection $units, Carbon $from, Carbon $to, array $holidayMap): array
    {
        $result = [];
        $cutoff = $to->greaterThan(Carbon::today()) ? Carbon::today() : $to->copy();

        foreach ($units as $unit) {
            $workdays = Pegawai::hariKerjaUnit($unit);
            $dates = [];
            $cursor = $from->copy();

            while ($cursor->lte($cutoff)) {
                $date = $cursor->toDateString();
                $dayName = $cursor->locale('id')->translatedFormat('l');
                $holiday = isset($holidayMap['national'][$date])
                    || isset($holidayMap['units'][$unit->id][$date]);

                if (in_array($dayName, $workdays, true) && ! $holiday) {
                    $dates[] = $date;
                }

                $cursor->addDay();
            }

            $result[$unit->id] = $dates;
        }

        return $result;
    }

    private function leaveMap(array $employeeIds, Carbon $from, Carbon $to): array
    {
        $result = [];

        PengajuanIzin::query()
            ->whereIn('pegawai_id', $employeeIds)
            ->where('status', 'disetujui')
            ->whereDate('tanggal_mulai', '<=', $to->toDateString())
            ->whereDate('tanggal_selesai', '>=', $from->toDateString())
            ->get(['pegawai_id', 'jenis_izin', 'tanggal_mulai', 'tanggal_selesai'])
            ->each(function (PengajuanIzin $izin) use (&$result) {
                $cursor = Carbon::parse($izin->tanggal_mulai);
                $end = Carbon::parse($izin->tanggal_selesai);

                while ($cursor->lte($end)) {
                    $result[$izin->pegawai_id][$cursor->toDateString()] = $izin->jenis_izin;
                    $cursor->addDay();
                }
            });

        return $result;
    }

    private function presensiMap(array $employeeIds, Carbon $from, Carbon $to): Collection
    {
        return Presensi::query()
            ->whereIn('pegawai_id', $employeeIds)
            ->whereBetween('tanggal', [$from->toDateString(), $to->toDateString()])
            ->where('is_lembur', false)
            ->with('pegawai:id,nama_lengkap')
            ->get(['id', 'pegawai_id', 'jadwal_id', 'tanggal', 'status', 'is_tugas_luar', 'tugas_luar_status'])
            ->groupBy(fn (Presensi $presensi) => $presensi->pegawai_id.'|'.Carbon::parse($presensi->tanggal)->toDateString());
    }

    private function schedules(array $employeeIds, array $unitIds, string $tahunAjaran, int $semester): Collection
    {
        return Jadwal::query()
            ->whereIn('pegawai_id', $employeeIds)
            ->whereIn('unit_sekolah_id', $unitIds)
            ->where('jenis_jadwal', 'mengajar')
            ->where('tahun_ajaran', $tahunAjaran)
            ->where('semester', $semester)
            ->with(['pegawai:id,nama_lengkap', 'pegawaiMapel.mataPelajaran:id,nama', 'unitSekolah:id,durasi_jp'])
            ->get(['id', 'pegawai_id', 'unit_sekolah_id', 'kelas_label', 'pegawai_mapel_id', 'hari', 'jam_mulai', 'jam_selesai']);
    }

    private function actualBySlot(Collection $presensiMap): array
    {
        $result = [];

        foreach ($presensiMap as $rows) {
            foreach ($rows as $presensi) {
                if (! $presensi->jadwal_id || ! in_array($presensi->status, ['hadir', 'telat'], true)) {
                    continue;
                }

                $key = Carbon::parse($presensi->tanggal)->toDateString().'|'.$presensi->jadwal_id;
                $result[$key] ??= $presensi;
            }
        }

        return $result;
    }

    private function piketEntries(array $unitIds, Carbon $from, Carbon $to): array
    {
        $result = [];

        $reports = LaporanPiket::query()
            ->whereIn('unit_sekolah_id', $unitIds)
            ->whereBetween('tanggal', [$from->toDateString(), $to->toDateString()])
            ->where('status', LaporanPiket::STATUS_SUBMITTED)
            ->get(['id', 'unit_sekolah_id', 'tanggal', 'items']);

        foreach ($reports as $report) {
            $date = Carbon::parse($report->tanggal)->toDateString();
            $entries = $report->items['kelas_kosong']['entries'] ?? [];

            foreach ($entries as $entry) {
                if (! empty($entry['slot_jadwal_id'])) {
                    $result['slot'][$date.'|'.$report->unit_sekolah_id.'|'.$entry['slot_jadwal_id']] = $entry;
                } else {
                    $key = $date.'|'.$report->unit_sekolah_id.'|'.($entry['kelas'] ?? '').'|'.($entry['guru_pegawai_id'] ?? '');
                    $result['manual'][$key] = $entry;
                }
            }
        }

        return $result;
    }

    private function piketForSlot(array $piket, string $date, int $unitId, Jadwal $slot): ?array
    {
        return $piket['slot'][$date.'|'.$unitId.'|'.$slot->id]
            ?? $piket['manual'][$date.'|'.$unitId.'|'.($slot->kelas_label ?? '').'|'.$slot->pegawai_id]
            ?? null;
    }

    private function dayStatus(Collection $rows, ?string $leave): string
    {
        if ($rows->contains(fn (Presensi $presensi) => $presensi->is_tugas_luar && $presensi->tugas_luar_status === 'disetujui')) {
            return 'tugas';
        }

        $attendance = $rows
            ->filter(fn (Presensi $presensi) => in_array($presensi->status, ['hadir', 'telat', 'sakit', 'izin', 'cuti'], true))
            ->sortBy(fn (Presensi $presensi) => self::STATUS_PRIORITY[$presensi->status] ?? 9)
            ->first();

        if ($attendance) {
            return $attendance->status;
        }

        if ($leave) {
            return $leave;
        }

        return $rows->contains('status', 'alpa') ? 'alpa' : 'alpa';
    }

    private function defaultTahunAjaran(Carbon $date): string
    {
        return $date->month >= 7
            ? $date->year.'/'.($date->year + 1)
            : ($date->year - 1).'/'.$date->year;
    }

    private function formatJp(float $value): string
    {
        $value = round($value, 2);

        return $value == floor($value)
            ? (string) (int) $value
            : number_format($value, 2, ',', '.');
    }
}
