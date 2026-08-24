<?php

namespace App\Services;

use App\Models\Pegawai;
use App\Models\PengajuanIzin;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use Carbon\Carbon;

class KcdReportService
{
    /**
     * Bangun data laporan KCD bulanan, dipecah per blok mingguan (Senin-Jumat).
     * Hari izin/sakit yang sudah koordinasi (dihitung_hadir_kcd=true) ditampilkan
     * sebagai HADIR berisi jam sekolah (jam_masuk_kantor - jam_pulang_kantor).
     */
    public function build(UnitSekolah $unit, string $periodeKey, ?int $minggu = null): array
    {
        $start = Carbon::parse($periodeKey.'-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $weeks = $this->splitWeeks($start, $end);

        if ($minggu !== null) {
            $weeks = array_values(array_filter($weeks, fn ($w, $i) => $i + 1 === $minggu, ARRAY_FILTER_USE_BOTH));
        }

        $pegawais = Pegawai::query()
            ->where('status_aktif', 'aktif')
            ->where('status_kepegawaian', 'tetap')
            ->whereHas('jabatans', fn ($q) => $q->where('is_guru', true))
            ->whereHas('units', fn ($q) => $q->where('unit_sekolah.id', $unit->id))
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap']);

        $pegawaiIds = $pegawais->pluck('id')->all();

        $presensis = collect();
        if (! empty($pegawaiIds)) {
            $presensis = Presensi::query()
                ->whereIn('pegawai_id', $pegawaiIds)
                ->whereBetween('tanggal', [$start->toDateString(), $end->toDateString()])
                ->where('is_lembur', false)
                ->get(['id', 'pegawai_id', 'tanggal', 'jam_masuk', 'jam_keluar']);
        }

        $izins = collect();
        if (! empty($pegawaiIds)) {
            $izins = PengajuanIzin::query()
                ->where('status', 'disetujui')
                ->where('dihitung_hadir_kcd', true)
                ->whereIn('pegawai_id', $pegawaiIds)
                ->where(function ($q) use ($start, $end) {
                    $q->whereBetween('tanggal_mulai', [$start, $end])
                        ->orWhereBetween('tanggal_selesai', [$start, $end])
                        ->orWhere(fn ($q2) => $q2->where('tanggal_mulai', '<=', $start)->where('tanggal_selesai', '>=', $end));
                })
                ->get(['id', 'pegawai_id', 'tanggal_mulai', 'tanggal_selesai']);
        }

        $presByPegawai = [];
        foreach ($presensis as $p) {
            $presByPegawai[$p->pegawai_id][$p->tanggal->toDateString()][] = $p;
        }

        $izinByPegawai = [];
        foreach ($izins as $i) {
            $cur = $i->tanggal_mulai->copy();
            $stop = $i->tanggal_selesai->copy();
            while ($cur <= $stop) {
                if ($cur >= $start && $cur <= $end) {
                    $izinByPegawai[$i->pegawai_id][$cur->toDateString()] = true;
                }
                $cur->addDay();
            }
        }

        $jamMasukKantor = $unit->jam_masuk_kantor ?? '07:30';
        $jamPulangKantor = $unit->jam_pulang_kantor ?? '15:00';

        $pegawaiData = [];
        $no = 1;
        foreach ($pegawais as $peg) {
            $days = [];
            foreach ($weeks as $week) {
                foreach ($week as $dateStr) {
                    $days[$dateStr] = $this->cellFor(
                        $peg->id,
                        $dateStr,
                        $presByPegawai,
                        $izinByPegawai,
                        $jamMasukKantor,
                        $jamPulangKantor
                    );
                }
            }
            $pegawaiData[] = [
                'no' => $no++,
                'nama' => $peg->nama_lengkap,
                'days' => $days,
            ];
        }

        $weekBlocks = [];
        foreach ($weeks as $week) {
            $weekBlocks[] = [
                'days' => array_map(function ($dateStr) {
                    $d = Carbon::parse($dateStr);

                    return [
                        'date' => $dateStr,
                        'label' => $d->translatedFormat('l'),
                        'short' => $d->format('d/m'),
                    ];
                }, $week),
            ];
        }

        return [
            'unit' => [
                'nama' => $unit->nama,
                'singkatan' => $unit->singkatan,
                'logo_path' => $this->logoLocalPath($unit->logo),
                'web' => $unit->web,
                'telepon' => $unit->telepon,
                'alamat' => $unit->alamat,
            ],
            'periode' => $start->translatedFormat('F Y'),
            'periode_key' => $periodeKey,
            'jam_masuk_kantor' => $jamMasukKantor,
            'jam_pulang_kantor' => $jamPulangKantor,
            'weeks' => $weekBlocks,
            'pegawai' => $pegawaiData,
        ];
    }

    /**
     * Pecah rentang bulan menjadi minggu-minggu (Mulai Senin), tiap minggu 5 hari kerja.
     */
    protected function splitWeeks(Carbon $start, Carbon $end): array
    {
        $weeks = [];
        $cursor = $start->copy()->startOfWeek(Carbon::MONDAY);

        while ($cursor <= $end) {
            $week = [];
            for ($i = 0; $i < 5; $i++) {
                $week[] = $cursor->copy()->addDays($i)->toDateString();
            }
            $weeks[] = $week;
            $cursor->addWeek();
        }

        return $weeks;
    }

    protected function cellFor(
        $pegId,
        $dateStr,
        array $presByPegawai,
        array $izinByPegawai,
        $jamMasukKantor,
        $jamPulangKantor
    ): array {
        if (! empty($izinByPegawai[$pegId][$dateStr])) {
            return [
                'masuk' => $this->fmt($jamMasukKantor),
                'pulang' => $this->fmt($jamPulangKantor),
                'koordinasi' => true,
            ];
        }

        $records = $presByPegawai[$pegId][$dateStr] ?? [];
        if (empty($records)) {
            return ['masuk' => '—', 'pulang' => '—', 'koordinasi' => false];
        }

        $masuk = null;
        $pulang = null;
        foreach ($records as $r) {
            if ($r->jam_masuk && ($masuk === null || $r->jam_masuk < $masuk)) {
                $masuk = $r->jam_masuk;
            }
            if ($r->jam_keluar && ($pulang === null || $r->jam_keluar > $pulang)) {
                $pulang = $r->jam_keluar;
            }
        }

        return [
            'masuk' => $masuk ? $this->fmt($masuk) : '—',
            'pulang' => $pulang ? $this->fmt($pulang) : '—',
            'koordinasi' => false,
        ];
    }

    protected function fmt($value): string
    {
        if (! $value) {
            return '—';
        }

        return Carbon::parse($value)->format('H.i');
    }

    /**
     * Resolve logo unit ke path file lokal (bukan URL) agar bisa dirender DOMPDF.
     */
    protected function logoLocalPath(?string $logo): ?string
    {
        if (! $logo) {
            return null;
        }

        $disk = config('filesystems.image_disk', 'public');
        $root = config("filesystems.disks.$disk.root");
        $path = rtrim($root, '/').'/'.ltrim($logo, '/');

        return file_exists($path) ? $path : null;
    }
}
