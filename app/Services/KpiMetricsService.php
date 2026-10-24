<?php

namespace App\Services;

use App\Models\HariLibur;
use App\Models\Jadwal;
use App\Models\Pegawai;
use App\Models\PengajuanIzin;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class KpiMetricsService
{
    public function actual(string $sourceKey, Pegawai $pegawai, UnitSekolah $unit, Carbon $start, Carbon $end): ?float
    {
        $assignment = (object) ['id' => -1, 'pegawai' => $pegawai, 'unitSekolah' => $unit];

        return $this->forAssignments(collect([$assignment]), $start, $end)[-1][$sourceKey] ?? null;
    }

    /** @return array<int|string, array{attendance_rate:?float,teaching_jp_rate:?float,hadir_hari:int,hari_kerja:int,teaching_jp_hadir:float,teaching_jp_terjadwal:float}> */
    public function forAssignments(Collection $assignments, Carbon $start, Carbon $end): array
    {
        if ($assignments->isEmpty()) {
            return [];
        }

        $pegawaiIds = $assignments->pluck('pegawai.id')->filter()->unique()->values();
        $units = $assignments->pluck('unitSekolah')->filter()->unique('id')->keyBy('id');
        $unitIds = $units->keys();
        $dates = [];
        for ($date = $start->copy()->startOfDay(); $date->lte($end); $date->addDay()) {
            $dates[] = $date->toDateString();
        }

        $nationalHolidays = [];
        $unitHolidays = [];
        HariLibur::whereDate('tanggal', '>=', $start->toDateString())
            ->whereDate('tanggal', '<=', $end->toDateString())
            ->where(fn ($q) => $q->whereNull('unit_sekolah_id')->orWhereIn('unit_sekolah_id', $unitIds))
            ->get(['tanggal', 'unit_sekolah_id'])
            ->each(function ($holiday) use (&$nationalHolidays, &$unitHolidays) {
                $date = Carbon::parse($holiday->tanggal)->toDateString();
                if ($holiday->unit_sekolah_id === null) {
                    $nationalHolidays[$date] = true;
                } else {
                    $unitHolidays[$holiday->unit_sekolah_id][$date] = true;
                }
            });

        $leaveDates = [];
        PengajuanIzin::whereIn('pegawai_id', $pegawaiIds)
            ->where('status', 'disetujui')
            ->whereDate('tanggal_mulai', '<=', $end->toDateString())
            ->whereDate('tanggal_selesai', '>=', $start->toDateString())
            ->get(['pegawai_id', 'tanggal_mulai', 'tanggal_selesai'])
            ->each(function ($leave) use (&$leaveDates, $start, $end) {
                $from = Carbon::parse($leave->tanggal_mulai)->max($start);
                $to = Carbon::parse($leave->tanggal_selesai)->min($end);
                for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
                    $leaveDates[$leave->pegawai_id][$date->toDateString()] = true;
                }
            });

        $presensi = Presensi::whereIn('pegawai_id', $pegawaiIds)
            ->whereIn('unit_sekolah_id', $unitIds)
            ->whereBetween('tanggal', [$start->toDateString(), $end->toDateString()])
            ->where('is_lembur', false)
            ->get(['pegawai_id', 'unit_sekolah_id', 'jadwal_id', 'tanggal', 'status'])
            ->groupBy(fn ($row) => $row->pegawai_id.'|'.$row->unit_sekolah_id);
        $presentSessions = [];
        foreach ($presensi as $assignmentKey => $rows) {
            foreach ($rows as $row) {
                if ($row->jadwal_id && in_array($row->status, ['hadir', 'telat'], true)) {
                    $presentSessions[$assignmentKey][$row->jadwal_id.'|'.Carbon::parse($row->tanggal)->toDateString()] = true;
                }
            }
        }

        $schedules = Jadwal::whereIn('pegawai_id', $pegawaiIds)
            ->whereIn('unit_sekolah_id', $unitIds)
            ->get(['id', 'pegawai_id', 'unit_sekolah_id', 'hari', 'jam_mulai', 'jam_selesai', 'jenis_jadwal'])
            ->groupBy(fn ($row) => $row->pegawai_id.'|'.$row->unit_sekolah_id);

        $dayMap = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
        $datesByDay = collect($dates)->groupBy(fn ($date) => $dayMap[Carbon::parse($date)->format('l')]);
        $results = [];
        foreach ($assignments as $assignment) {
            $pegawai = $assignment->pegawai;
            $unit = $assignment->unitSekolah;
            if (! $pegawai || ! $unit) {
                continue;
            }

            $key = $pegawai->id.'|'.$unit->id;
            $allowedDays = Pegawai::hariKerjaUnit($unit);
            $validDates = [];
            $unitWorkingDates = [];
            foreach ($dates as $date) {
                $carbon = Carbon::parse($date);
                if (isset($nationalHolidays[$date])
                    || isset($unitHolidays[$unit->id][$date])
                    || isset($leaveDates[$pegawai->id][$date])) {
                    continue;
                }
                $validDates[] = $date;
                if (in_array($dayMap[$carbon->format('l')], $allowedDays, true)) {
                    $unitWorkingDates[] = $date;
                }
            }

            $rows = $presensi->get($key, collect());
            $hadirDates = $rows->whereIn('status', ['hadir', 'telat'])
                ->map(fn ($row) => Carbon::parse($row->tanggal)->toDateString())
                ->unique()
                ->all();
            $onTimeDates = $rows->where('status', 'hadir')
                ->map(fn ($row) => Carbon::parse($row->tanggal)->toDateString())
                ->unique()
                ->all();
            $schedulesForAssignment = $schedules->get($key, collect());
            $scheduledDates = [];
            foreach ($schedulesForAssignment as $schedule) {
                foreach ($datesByDay->get($schedule->hari, []) as $date) {
                    if (in_array($date, $validDates, true)) {
                        $scheduledDates[$date] = true;
                    }
                }
            }
            $expectedDates = $pegawai->wajib_kantor
                ? array_values(array_unique(array_merge($unitWorkingDates, array_keys($scheduledDates))))
                : array_keys($scheduledDates);
            $attendanceRate = $expectedDates !== []
                ? round(count(array_intersect($expectedDates, $hadirDates)) * 100 / count($expectedDates), 2)
                : null;
            $presentExpectedDates = array_intersect($expectedDates, $hadirDates);
            $punctualityRate = $presentExpectedDates !== []
                ? round(count(array_intersect($presentExpectedDates, $onTimeDates)) * 100 / count($presentExpectedDates), 2)
                : null;

            $plannedJp = 0.0;
            $completedJp = 0.0;
            $durasiJp = max(1, (int) ($unit->durasi_jp ?: 45));
            $validDateSet = array_flip($validDates);
            foreach ($schedulesForAssignment->where('jenis_jadwal', 'mengajar') as $schedule) {
                $jp = max(0, Carbon::parse($schedule->jam_mulai)->diffInMinutes(Carbon::parse($schedule->jam_selesai))) / $durasiJp;
                foreach ($datesByDay->get($schedule->hari, []) as $date) {
                    if (! isset($validDateSet[$date])) {
                        continue;
                    }
                    $plannedJp += $jp;
                    if (isset($presentSessions[$key][$schedule->id.'|'.$date])) {
                        $completedJp += $jp;
                    }
                }
            }

            $results[$assignment->id] = [
                'attendance_rate' => $attendanceRate,
                'punctuality_rate' => $punctualityRate,
                'teaching_jp_rate' => $plannedJp > 0 ? round(min(100, $completedJp * 100 / $plannedJp), 2) : null,
                'hadir_hari' => count($presentExpectedDates),
                'hari_kerja' => count($expectedDates),
                'teaching_jp_hadir' => round($completedJp, 2),
                'teaching_jp_terjadwal' => round($plannedJp, 2),
            ];
        }

        return $results;
    }
}
