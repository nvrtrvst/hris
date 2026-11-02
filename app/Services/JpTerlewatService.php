<?php

namespace App\Services;

use App\Models\HariLibur;
use App\Models\Jadwal;
use App\Models\PengajuanIzin;
use App\Models\Presensi;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class JpTerlewatService
{
    private const HARI = [
        'Sunday' => 'Minggu',
        'Monday' => 'Senin',
        'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis',
        'Friday' => 'Jumat',
        'Saturday' => 'Sabtu',
    ];

    /**
     * @return array{rows: Collection, stats: array{total: int, belum_presensi: int, alpa_otomatis: int, alpa_manual: int}}
     */
    public function build(
        User $user,
        Carbon $start,
        Carbon $end,
        ?int $unitId = null,
        ?string $search = null
    ): array {
        $empty = [
            'rows' => collect(),
            'stats' => ['total' => 0, 'belum_presensi' => 0, 'alpa_otomatis' => 0, 'alpa_manual' => 0],
        ];

        if (! $user->can('view_all_units') && ! $user->unit_sekolah_id) {
            return $empty;
        }

        $dates = $this->dates($start, $end);
        $jadwalQuery = Jadwal::query()
            ->with([
                'pegawai:id,nama_lengkap,nuptk,status_aktif',
                'unitSekolah:id,nama,singkatan',
                'pegawaiMapel.mataPelajaran:id,nama',
            ])
            ->where('jenis_jadwal', 'mengajar')
            ->whereIn('hari', array_values(self::HARI))
            ->whereNotNull('unit_sekolah_id')
            ->whereHas('pegawai', fn ($q) => $q->where('status_aktif', 'aktif'))
            ->whereHas('pegawai', function ($q) use ($user, $search) {
                if (! $user->can('view_all_units')) {
                    $q->forUnit($user->unit_sekolah_id);
                }
                if ($search) {
                    $q->where('nama_lengkap', 'like', '%'.$search.'%');
                }
            });

        if ($user->can('view_all_units') && $unitId) {
            $jadwalQuery->where('unit_sekolah_id', $unitId);
        } elseif (! $user->can('view_all_units')) {
            $jadwalQuery->where('unit_sekolah_id', $user->unit_sekolah_id);
        }

        $jadwals = $jadwalQuery->get();

        if ($jadwals->isEmpty()) {
            return $empty;
        }

        $pegawaiIds = $jadwals->pluck('pegawai_id')->unique()->values();
        $jadwalIds = $jadwals->pluck('id')->unique()->values();

        $presensis = Presensi::query()
            ->whereIn('jadwal_id', $jadwalIds)
            ->whereDate('tanggal', '>=', $start->toDateString())
            ->whereDate('tanggal', '<=', $end->toDateString())
            ->get([
                'id',
                'pegawai_id',
                'jadwal_id',
                'tanggal',
                'tipe_presensi',
                'status',
                'jam_masuk',
                'jam_keluar',
                'keterangan',
                'is_lembur',
                'is_tugas_luar',
            ])
            ->keyBy(fn (Presensi $presensi) => $this->presensiKey($presensi));

        $izins = PengajuanIzin::query()
            ->whereIn('pegawai_id', $pegawaiIds)
            ->where('status', 'disetujui')
            ->whereDate('tanggal_mulai', '<=', $end->toDateString())
            ->whereDate('tanggal_selesai', '>=', $start->toDateString())
            ->get(['pegawai_id', 'tanggal_mulai', 'tanggal_selesai']);

        $tugas = Presensi::query()
            ->whereIn('pegawai_id', $pegawaiIds)
            ->whereDate('tanggal', '>=', $start->toDateString())
            ->whereDate('tanggal', '<=', $end->toDateString())
            ->whereIn('tipe_presensi', ['tugas_luar', 'tugas_kantor'])
            ->where('status', '!=', 'alpa')
            ->get(['pegawai_id', 'tanggal'])
            ->keyBy(fn (Presensi $presensi) => $presensi->pegawai_id.'|'.$this->dateString($presensi->tanggal));

        $hariLibur = HariLibur::query()
            ->whereDate('tanggal', '>=', $start->toDateString())
            ->whereDate('tanggal', '<=', $end->toDateString())
            ->get(['tanggal', 'unit_sekolah_id']);

        $jadwalByHari = $jadwals->groupBy('hari');
        $rows = collect();

        foreach ($dates as $date) {
            $tanggal = $date['date'];
            $hari = $date['hari'];

            foreach ($jadwalByHari->get($hari, collect()) as $jadwal) {
                if (! $this->periodMatches($jadwal, $date['tahun_ajaran'], $date['semester'])) {
                    continue;
                }

                if (Carbon::parse($tanggal.' '.$jadwal->jam_selesai)->isFuture()) {
                    continue;
                }

                if ($this->isHoliday($hariLibur, $tanggal, $jadwal->unit_sekolah_id)) {
                    continue;
                }

                if ($this->hasIzin($izins, $jadwal->pegawai_id, $tanggal)) {
                    continue;
                }

                if ($tugas->has($jadwal->pegawai_id.'|'.$tanggal)) {
                    continue;
                }

                $presensi = $presensis->get($jadwal->pegawai_id.'|'.$jadwal->id.'|'.$tanggal);

                if ($presensi && ! $this->isCorrectable($presensi)) {
                    continue;
                }

                $sumber = $presensi ? $this->alpaSource($presensi) : 'belum_presensi';

                $rows->push([
                    'presensi_id' => $presensi?->id,
                    'tanggal' => $tanggal,
                    'pegawai_id' => $jadwal->pegawai_id,
                    'pegawai' => $jadwal->pegawai?->only(['id', 'nama_lengkap', 'nuptk']),
                    'unit_sekolah_id' => $jadwal->unit_sekolah_id,
                    'unit' => $jadwal->unitSekolah?->singkatan ?? $jadwal->unitSekolah?->nama,
                    'jadwal_id' => $jadwal->id,
                    'mapel' => $jadwal->pegawaiMapel?->mataPelajaran?->nama,
                    'kelas' => $jadwal->kelas_label,
                    'jam_mulai' => substr((string) $jadwal->jam_mulai, 0, 5),
                    'jam_selesai' => substr((string) $jadwal->jam_selesai, 0, 5),
                    'status_lama' => $presensi?->status ?? 'belum_presensi',
                    'jam_masuk_lama' => $presensi?->jam_masuk,
                    'jam_keluar_lama' => $presensi?->jam_keluar,
                    'sumber' => $sumber,
                    'sumber_label' => match ($sumber) {
                        'alpa_otomatis' => 'Alpa otomatis',
                        'alpa_manual' => 'Alpa manual',
                        default => 'Belum presensi',
                    },
                ]);
            }
        }

        $rows = $rows
            ->sort(function (array $a, array $b) {
                return [$b['tanggal'], (string) $a['pegawai']['nama_lengkap'], $a['jam_mulai']]
                    <=> [$a['tanggal'], (string) $b['pegawai']['nama_lengkap'], $b['jam_mulai']];
            })
            ->values();

        return [
            'rows' => $rows,
            'stats' => [
                'total' => $rows->count(),
                'belum_presensi' => $rows->where('sumber', 'belum_presensi')->count(),
                'alpa_otomatis' => $rows->where('sumber', 'alpa_otomatis')->count(),
                'alpa_manual' => $rows->where('sumber', 'alpa_manual')->count(),
            ],
        ];
    }

    private function dates(Carbon $start, Carbon $end): array
    {
        $dates = [];

        for ($cursor = $start->copy()->startOfDay(); $cursor->lte($end); $cursor->addDay()) {
            $dates[] = [
                'date' => $cursor->toDateString(),
                'hari' => self::HARI[$cursor->format('l')] ?? null,
                'tahun_ajaran' => $this->tahunAjaran($cursor),
                'semester' => $cursor->month >= 7 ? 1 : 2,
            ];
        }

        return $dates;
    }

    private function tahunAjaran(Carbon $date): string
    {
        return $date->month >= 7
            ? $date->year.'/'.($date->year + 1)
            : ($date->year - 1).'/'.$date->year;
    }

    private function periodMatches(Jadwal $jadwal, string $tahunAjaran, int $semester): bool
    {
        return (string) $jadwal->tahun_ajaran === $tahunAjaran
            && (int) $jadwal->semester === $semester;
    }

    private function isCorrectable(Presensi $presensi): bool
    {
        if ($presensi->is_lembur || $presensi->is_tugas_luar) {
            return false;
        }

        if (in_array($presensi->status, ['hadir', 'telat', 'izin', 'sakit', 'cuti'], true)) {
            return false;
        }

        return $presensi->status === 'alpa' && $presensi->tipe_presensi === 'mengajar';
    }

    private function alpaSource(Presensi $presensi): string
    {
        return str_contains((string) $presensi->keterangan, 'Tidak hadir (otomatis)')
            ? 'alpa_otomatis'
            : 'alpa_manual';
    }

    private function isHoliday(Collection $hariLibur, string $tanggal, int $unitId): bool
    {
        return $hariLibur->contains(function (HariLibur $libur) use ($tanggal, $unitId) {
            return $this->dateString($libur->tanggal) === $tanggal
                && ($libur->unit_sekolah_id === null || (int) $libur->unit_sekolah_id === $unitId);
        });
    }

    private function hasIzin(Collection $izins, int $pegawaiId, string $tanggal): bool
    {
        return $izins->contains(function (PengajuanIzin $izin) use ($pegawaiId, $tanggal) {
            return (int) $izin->pegawai_id === $pegawaiId
                && $this->dateString($izin->tanggal_mulai) <= $tanggal
                && $this->dateString($izin->tanggal_selesai) >= $tanggal;
        });
    }

    private function presensiKey(Presensi $presensi): string
    {
        return $presensi->pegawai_id.'|'.$presensi->jadwal_id.'|'.$this->dateString($presensi->tanggal);
    }

    private function dateString($date): string
    {
        return $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : (string) $date;
    }
}
