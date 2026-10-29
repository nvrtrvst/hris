<?php

namespace App\Services;

use App\Models\PegawaiKelasBinaan;
use App\Models\PiketSiswaTerlambat;
use App\Models\UnitSekolah;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class KeterlambatanSiswaReportService
{
    public function report(User $user, array $filters): array
    {
        $start = Carbon::parse($filters['start_date'] ?? Carbon::today());
        $end = Carbon::parse($filters['end_date'] ?? $start);
        if ($start->diffInDays($end) > 31) {
            $start = $end->copy()->subDays(31);
        }

        $scope = $this->scope($user, $filters['unit_id'] ?? null);
        $classFilter = trim((string) ($filters['class_label'] ?? ''));
        $search = trim((string) ($filters['search'] ?? ''));

        if ($classFilter !== '' && $scope['class_labels'] !== null && ! in_array($classFilter, $scope['class_labels'], true)) {
            abort(403, 'Kelas ini bukan kelas binaan Anda.');
        }

        $query = PiketSiswaTerlambat::with(['pegawai:id,nama_lengkap', 'unitSekolah:id,nama'])
            ->whereBetween('tanggal', [$start->toDateString(), $end->toDateString()])
            ->when($scope['unit_ids'] !== null, fn ($q) => $q->whereIn('unit_sekolah_id', $scope['unit_ids']))
            ->when($scope['class_labels'] !== null, fn ($q) => $q->whereIn('class_label', $scope['class_labels']))
            ->when($classFilter !== '', fn ($q) => $q->where('class_label', $classFilter))
            ->when($search !== '', fn ($q) => $q->where(function ($sub) use ($search) {
                $sub->where('nis', 'like', "%{$search}%")->orWhere('nama_snapshot', 'like', "%{$search}%");
            }))
            ->orderBy('tanggal')
            ->orderBy('jam_terlambat')
            ->orderBy('class_label');

        $rows = $query->get()->map(fn (PiketSiswaTerlambat $row) => [
            'id' => $row->id,
            'tanggal' => $row->tanggal->format('Y-m-d'),
            'unit' => $row->unitSekolah?->nama,
            'class_label' => $row->class_label,
            'nis' => $row->nis,
            'nama' => $row->nama_snapshot,
            'jam' => substr($row->jam_terlambat, 0, 5),
            'petugas' => $row->pegawai?->nama_lengkap,
            'keterangan' => $row->keterangan,
        ]);

        $classes = $this->classes($scope, $start, $end);
        $summary = [
            'total' => $rows->count(),
            'kelas' => $rows->pluck('class_label')->unique()->count(),
            'siswa' => $rows->pluck('nis')->unique()->count(),
        ];
        $perClass = $rows->groupBy('class_label')->map(fn (Collection $group, $class) => [
            'class_label' => $class,
            'total' => $group->count(),
        ])->values();
        $perDay = $rows->groupBy('tanggal')->map(fn (Collection $group, $date) => [
            'tanggal' => $date,
            'total' => $group->count(),
        ])->values();

        return [
            'filters' => [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'unit_id' => $scope['unit_id'],
                'class_label' => $classFilter,
                'search' => $search,
            ],
            'rows' => $rows,
            'summary' => $summary,
            'perClass' => $perClass,
            'perDay' => $perDay,
            'classes' => $classes,
            'units' => $this->unitsFor($user),
            'canFilterUnit' => $user->can('view_all_units'),
            'scope_label' => $scope['class_labels'] === null ? null : 'Kelas binaan',
        ];
    }

    public function exportRows(array $report): array
    {
        return [
            ['Tanggal', 'Unit', 'Kelas', 'NIS', 'Nama', 'Jam', 'Petugas', 'Keterangan'],
            $report['rows']->map(fn ($row) => [
                'tanggal' => $row['tanggal'],
                'unit' => $row['unit'],
                'kelas' => $row['class_label'],
                'nis' => $row['nis'],
                'nama' => $row['nama'],
                'jam' => $row['jam'],
                'petugas' => $row['petugas'],
                'keterangan' => $row['keterangan'] ?? '',
            ])->all(),
        ];
    }

    private function scope(User $user, ?int $unitId): array
    {
        if ($user->can('view_all_units')) {
            return ['unit_ids' => $unitId ? [(int) $unitId] : null, 'class_labels' => null, 'unit_id' => $unitId];
        }

        if (! $user->can('view_piket') && ! $user->can('view_presensi') && ! $user->can('view_absen_siswa')) {
            abort(403, 'Akses ditolak.');
        }

        if (! $user->can('view_piket') && ! $user->can('view_presensi') && $user->can('view_absen_siswa')) {
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
            'unit_id' => $unitId ?: $user->unit_sekolah_id,
        ];
    }

    private function classes(array $scope, Carbon $start, Carbon $end): Collection
    {
        $fromRows = PiketSiswaTerlambat::whereBetween('tanggal', [$start->toDateString(), $end->toDateString()])
            ->when($scope['unit_ids'] !== null, fn ($q) => $q->whereIn('unit_sekolah_id', $scope['unit_ids']))
            ->pluck('class_label');

        return collect($scope['class_labels'] ?? [])
            ->merge($fromRows)
            ->filter()
            ->unique()
            ->sort()
            ->values();
    }

    private function unitsFor(User $user)
    {
        if ($user->can('view_all_units')) {
            return UnitSekolah::orderBy('nama')->get(['id', 'nama']);
        }

        if ($user->hasRole('wali_kelas') && ! $user->can('view_presensi') && ! $user->can('view_piket')) {
            $ids = PegawaiKelasBinaan::aktif()
                ->where('pegawai_id', $user->pegawai?->id)
                ->pluck('unit_sekolah_id')
                ->unique()
                ->all();

            return UnitSekolah::whereIn('id', $ids)->orderBy('nama')->get(['id', 'nama']);
        }

        return UnitSekolah::whereKey($user->unit_sekolah_id)->get(['id', 'nama']);
    }
}
