<?php

namespace App\Services;

use App\Models\AbsenSiswaDetail;
use App\Models\AbsenSiswaSesi;
use App\Models\PegawaiKelasBinaan;
use App\Models\UnitSekolah;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AbsenSiswaReportService
{
    public const STATUSES = ['hadir', 'telat', 'izin', 'sakit', 'alpa'];

    public function report(User $user, array $filters): array
    {
        $mode = ($filters['mode'] ?? 'daily') === 'period' ? 'period' : 'daily';
        $start = $mode === 'daily'
            ? Carbon::today()
            : Carbon::parse($filters['start_date'] ?? Carbon::today());
        $end = $mode === 'daily'
            ? Carbon::today()
            : Carbon::parse($filters['end_date'] ?? $start);

        $scope = $this->scope($user, $filters['unit_id'] ?? null);
        $classOptions = $this->classOptions($scope, $start, $end);
        $classFilter = trim((string) ($filters['class_label'] ?? ''));

        if ($classFilter !== '' && $scope['class_labels'] !== null && ! in_array($classFilter, $scope['class_labels'], true)) {
            abort(403, 'Kelas ini bukan kelas binaan Anda.');
        }

        $base = $this->sessionQuery($scope, $start, $end, $classFilter);
        $summary = [
            'sesi' => (clone $base)->count(),
            'siswa' => 0,
            'hadir' => 0,
            'telat' => 0,
            'izin' => 0,
            'sakit' => 0,
            'alpa' => 0,
            'persen' => 0,
        ];

        if ($mode === 'daily') {
            $allNis = [];
            $sessions = $base->with([
                'pegawai:id,nama_lengkap',
                'unitSekolah:id,nama',
                'details',
            ])->orderBy('class_label')->get()->map(function (AbsenSiswaSesi $session) use (&$summary, &$allNis) {
                $allNis = array_merge($allNis, $session->details->pluck('nis')->all());
                $counts = $session->details->countBy('status');
                $summary['hadir'] += $counts->get('hadir', 0);
                $summary['telat'] += $counts->get('telat', 0);
                $summary['izin'] += $counts->get('izin', 0);
                $summary['sakit'] += $counts->get('sakit', 0);
                $summary['alpa'] += $counts->get('alpa', 0);

                return [
                    'id' => $session->id,
                    'class_id' => $session->class_id,
                    'class_label' => $session->class_label,
                    'unit' => $session->unitSekolah?->nama,
                    'pegawai' => $session->pegawai?->nama_lengkap,
                    'done_at' => $session->done_at?->format('H:i'),
                    'counts' => [
                        'hadir' => $counts->get('hadir', 0),
                        'telat' => $counts->get('telat', 0),
                        'izin' => $counts->get('izin', 0),
                        'sakit' => $counts->get('sakit', 0),
                        'alpa' => $counts->get('alpa', 0),
                    ],
                    'total' => $session->details->count(),
                    'non_hadir' => $session->details
                        ->where('status', '!=', 'hadir')
                        ->map(fn (AbsenSiswaDetail $detail) => [
                            'nis' => $detail->nis,
                            'nama' => $detail->nama_snapshot,
                            'status' => $detail->status,
                            'keterangan' => $detail->keterangan,
                        ])
                        ->values(),
                ];
            });

            $summary['siswa'] = count(array_unique($allNis));

            $summary['persen'] = $this->persen($summary['hadir'], $summary['telat'], $summary['hadir'] + $summary['telat'] + $summary['izin'] + $summary['sakit'] + $summary['alpa']);

            return [
                'mode' => 'daily',
                'filters' => $this->normalizedFilters($mode, $start, $end, $filters),
                'summary' => $summary,
                'sessions' => $sessions,
                'students' => [],
                'classes' => $classOptions,
                'classSummary' => [],
                'perhatian' => [],
            ];
        }

        $students = $this->periodStudents($scope, $start, $end, $classFilter, $filters);
        $summary = $this->summaryFromStudents($students, (clone $base)->count());
        $classSummary = $this->classSummary($students);
        $perhatian = $students
            ->filter(fn ($s) => $s['izin'] + $s['sakit'] + $s['alpa'] + $s['telat'] > 0)
            ->sortByDesc(fn ($s) => [$s['alpa'], $s['sakit'], $s['izin'], $s['telat']])
            ->values()
            ->take(50);

        return [
            'mode' => 'period',
            'filters' => $this->normalizedFilters($mode, $start, $end, $filters),
            'summary' => $summary,
            'sessions' => [],
            'students' => $students->values(),
            'classes' => $classOptions,
            'classSummary' => $classSummary,
            'perhatian' => $perhatian,
        ];
    }

    public function detail(User $user, array $filters): array
    {
        $nis = trim((string) ($filters['nis'] ?? ''));
        abort_if($nis === '', 422, 'NIS wajib diisi.');

        $start = Carbon::parse($filters['start_date'] ?? Carbon::today());
        $end = Carbon::parse($filters['end_date'] ?? $start);
        $scope = $this->scope($user, $filters['unit_id'] ?? null);
        $classFilter = trim((string) ($filters['class_label'] ?? ''));

        if ($classFilter !== '' && $scope['class_labels'] !== null && ! in_array($classFilter, $scope['class_labels'], true)) {
            abort(403, 'Kelas ini bukan kelas binaan Anda.');
        }

        $sessions = $this->sessionQuery($scope, $start, $end, $classFilter)
            ->with([
                'pegawai:id,nama_lengkap',
                'unitSekolah:id,nama',
                'details' => fn ($q) => $q->where('nis', $nis),
                'revisi.user:id,name',
            ])
            ->whereHas('details', fn ($q) => $q->where('nis', $nis))
            ->orderBy('tanggal')
            ->orderBy('class_label')
            ->get();

        $rows = $sessions->flatMap(function (AbsenSiswaSesi $session) {
            return $session->details->map(fn (AbsenSiswaDetail $detail) => [
                'tanggal' => $session->tanggal->format('Y-m-d'),
                'class_label' => $session->class_label,
                'unit' => $session->unitSekolah?->nama,
                'status' => $detail->status,
                'keterangan' => $detail->keterangan,
                'petugas' => $session->pegawai?->nama_lengkap,
                'done_at' => $session->done_at?->format('H:i'),
                'nis' => $detail->nis,
                'nama' => $detail->nama_snapshot,
            ]);
        })->values();

        $revisi = $sessions->flatMap(fn (AbsenSiswaSesi $session) => $session->revisi->map(fn ($r) => [
            'tanggal' => $session->tanggal->format('Y-m-d'),
            'kelas' => $session->class_label,
            'peran' => $r->peran,
            'oleh' => $r->user?->name,
            'alasan' => $r->alasan,
            'created_at' => $r->created_at?->format('Y-m-d H:i'),
        ]))->sortByDesc('created_at')->values();

        return [
            'siswa' => [
                'nis' => $nis,
                'nama' => $rows->first()['nama'] ?? null,
                'class_label' => $rows->last()['class_label'] ?? null,
            ],
            'rows' => $rows,
            'revisi' => $revisi,
        ];
    }

    private function scope(User $user, ?int $unitId): array
    {
        if ($user->can('view_all_units')) {
            return [
                'unit_ids' => $unitId ? [(int) $unitId] : null,
                'class_labels' => null,
                'unit_id' => $unitId,
            ];
        }

        if ($user->hasRole('wali_kelas') && ! $user->can('view_presensi')) {
            $mappings = PegawaiKelasBinaan::aktif()
                ->where('pegawai_id', $user->pegawai?->id)
                ->when($unitId, fn ($q) => $q->where('unit_sekolah_id', $unitId))
                ->get(['unit_sekolah_id', 'class_label']);

            return [
                'unit_ids' => $mappings->pluck('unit_sekolah_id')->unique()->values()->all(),
                'class_labels' => $mappings->pluck('class_label')->unique()->values()->all(),
                'unit_id' => $unitId,
            ];
        }

        return [
            'unit_ids' => $user->unit_sekolah_id ? [(int) $user->unit_sekolah_id] : [],
            'class_labels' => null,
            'unit_id' => $unitId,
        ];
    }

    private function sessionQuery(array $scope, Carbon $start, Carbon $end, string $classFilter)
    {
        return AbsenSiswaSesi::query()
            ->whereBetween('tanggal', [$start->toDateString(), $end->toDateString()])
            ->when($scope['unit_ids'] !== null, fn ($q) => $q->whereIn('unit_sekolah_id', $scope['unit_ids']))
            ->when($scope['class_labels'] !== null, fn ($q) => $q->whereIn('class_label', $scope['class_labels']))
            ->when($classFilter !== '', fn ($q) => $q->where('class_label', $classFilter));
    }

    private function classOptions(array $scope, Carbon $start, Carbon $end): Collection
    {
        $fromSessions = AbsenSiswaSesi::query()
            ->whereBetween('tanggal', [$start->toDateString(), $end->toDateString()])
            ->when($scope['unit_ids'] !== null, fn ($q) => $q->whereIn('unit_sekolah_id', $scope['unit_ids']))
            ->pluck('class_label');

        return collect($scope['class_labels'] ?? [])
            ->merge($fromSessions)
            ->filter()
            ->unique()
            ->sort()
            ->values();
    }

    private function periodStudents(array $scope, Carbon $start, Carbon $end, string $classFilter, array $filters): Collection
    {
        $query = DB::table('absen_siswa_detail as d')
            ->join('absen_siswa_sesi as s', 's.id', '=', 'd.absen_siswa_sesi_id')
            ->whereBetween('s.tanggal', [$start->toDateString(), $end->toDateString()])
            ->when($scope['unit_ids'] !== null, fn ($q) => $q->whereIn('s.unit_sekolah_id', $scope['unit_ids']))
            ->when($scope['class_labels'] !== null, fn ($q) => $q->whereIn('s.class_label', $scope['class_labels']))
            ->when($classFilter !== '', fn ($q) => $q->where('s.class_label', $classFilter))
            ->when(trim((string) ($filters['search'] ?? '')) !== '', function ($q) use ($filters) {
                $search = '%'.trim((string) $filters['search']).'%';
                $q->where(function ($sub) use ($search) {
                    $sub->where('d.nis', 'like', $search)
                        ->orWhere('d.nama_snapshot', 'like', $search);
                });
            })
            ->when(in_array($filters['status'] ?? null, self::STATUSES, true), function ($q) use ($filters) {
                $q->havingRaw(
                    'SUM(CASE WHEN d.status = ? THEN 1 ELSE 0 END) > 0',
                    [$filters['status']],
                );
            })
            ->selectRaw("s.unit_sekolah_id as unit_id, d.nis, MAX(d.nama_snapshot) as nama, MAX(s.class_label) as class_label,
                COUNT(DISTINCT s.id) as sesi_tercatat,
                SUM(CASE WHEN d.status = 'hadir' THEN 1 ELSE 0 END) as hadir,
                SUM(CASE WHEN d.status = 'telat' THEN 1 ELSE 0 END) as telat,
                SUM(CASE WHEN d.status = 'izin' THEN 1 ELSE 0 END) as izin,
                SUM(CASE WHEN d.status = 'sakit' THEN 1 ELSE 0 END) as sakit,
                SUM(CASE WHEN d.status = 'alpa' THEN 1 ELSE 0 END) as alpa")
            ->groupBy('s.unit_sekolah_id', 'd.nis')
            ->orderByRaw("SUM(CASE WHEN d.status = 'alpa' THEN 1 ELSE 0 END) DESC")
            ->orderByRaw("SUM(CASE WHEN d.status = 'sakit' THEN 1 ELSE 0 END) DESC")
            ->orderBy('d.nis');

        $rows = $query->get();
        $unitNames = UnitSekolah::whereIn('id', $rows->pluck('unit_id')->unique())->pluck('nama', 'id');

        return $rows->map(function ($row) use ($unitNames) {
            $total = (int) $row->hadir + (int) $row->telat + (int) $row->izin + (int) $row->sakit + (int) $row->alpa;

            return [
                'unit_id' => (int) $row->unit_id,
                'unit' => $unitNames->get($row->unit_id),
                'nis' => $row->nis,
                'nama' => $row->nama,
                'class_label' => $row->class_label,
                'sesi_tercatat' => (int) $row->sesi_tercatat,
                'hadir' => (int) $row->hadir,
                'telat' => (int) $row->telat,
                'izin' => (int) $row->izin,
                'sakit' => (int) $row->sakit,
                'alpa' => (int) $row->alpa,
                'persen' => $this->persen((int) $row->hadir, (int) $row->telat, $total),
            ];
        });
    }

    private function summaryFromStudents(Collection $students, int $sessionCount): array
    {
        $summary = [
            'sesi' => $sessionCount,
            'siswa' => $students->count(),
            'hadir' => (int) $students->sum('hadir'),
            'telat' => (int) $students->sum('telat'),
            'izin' => (int) $students->sum('izin'),
            'sakit' => (int) $students->sum('sakit'),
            'alpa' => (int) $students->sum('alpa'),
        ];
        $summary['persen'] = $this->persen($summary['hadir'], $summary['telat'], $summary['hadir'] + $summary['telat'] + $summary['izin'] + $summary['sakit'] + $summary['alpa']);

        return $summary;
    }

    private function classSummary(Collection $students): Collection
    {
        return $students->groupBy('class_label')->map(fn (Collection $rows, $class) => [
            'class_label' => $class,
            'siswa' => $rows->count(),
            'hadir' => (int) $rows->sum('hadir'),
            'telat' => (int) $rows->sum('telat'),
            'izin' => (int) $rows->sum('izin'),
            'sakit' => (int) $rows->sum('sakit'),
            'alpa' => (int) $rows->sum('alpa'),
        ])->values();
    }

    private function persen(int $hadir, int $telat, int $total): float
    {
        return $total > 0 ? round((($hadir + $telat) / $total) * 100, 1) : 0.0;
    }

    private function normalizedFilters(string $mode, Carbon $start, Carbon $end, array $filters): array
    {
        return [
            'mode' => $mode,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'unit_id' => $filters['unit_id'] ?? null,
            'class_label' => $filters['class_label'] ?? '',
            'status' => $filters['status'] ?? 'semua',
            'search' => $filters['search'] ?? '',
        ];
    }
}
