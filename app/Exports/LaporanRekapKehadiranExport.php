<?php

namespace App\Exports;

use App\Constants\BobotKehadiran;
use App\Models\HariLibur;
use App\Models\Pegawai;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class LaporanRekapKehadiranExport implements FromCollection, ShouldAutoSize, WithCustomStartCell, WithEvents, WithHeadings, WithMapping
{
    use FormulaEscapable;

    protected $start_date;

    protected $end_date;

    protected $unit_id;

    protected $jenis;

    protected $search;

    protected $status;

    /** @var Collection<int, array{pegawai: Pegawai, hadir: int, telat: int, sakit: int, izin: int, cuti: int, alpa: int, hariKerja: int, persen: int}> */
    protected $rekap;

    public function __construct($start_date, $end_date, $unit_id = null, $jenis = null, $search = null, $status = null)
    {
        $this->start_date = $start_date;
        $this->end_date = $end_date;
        $this->unit_id = $unit_id;
        $this->jenis = $jenis;
        $this->search = $search;
        $this->status = $status;

        $this->buildRekap();
    }

    /**
     * Build per-pegawai rekap: 1 pegawai = 1 row.
     */
    private function buildRekap(): void
    {
        $start = Carbon::parse($this->start_date);
        $end = Carbon::parse($this->end_date);

        // 1. Fetch presensi rows.
        $query = Presensi::with(['pegawai.jabatans', 'pegawai.units'])
            ->whereBetween('tanggal', [$this->start_date, $this->end_date])
            ->where('is_lembur', false);

        if ($this->unit_id) {
            $query->where('unit_sekolah_id', $this->unit_id);
        }

        if ($this->jenis === 'pendidik') {
            $query->whereHas('pegawai', fn ($q) => $q->guru());
        } elseif ($this->jenis === 'kependidikan') {
            $query->whereHas('pegawai', fn ($q) => $q->nonGuru());
        }

        if ($this->search !== null && $this->search !== '') {
            $query->whereHas('pegawai', fn ($q) => $q->where('nama_lengkap', 'like', '%'.$this->search.'%'));
        }

        $rows = $query->get(['pegawai_id', 'tanggal', 'status', 'jadwal_id', 'unit_sekolah_id']);

        // Sertakan pegawai aktif dalam scope walau belum memiliki record presensi.
        $roster = Pegawai::query()->where('status_aktif', 'aktif')->whereHas('units');
        if ($this->unit_id) {
            $roster->whereHas('units', fn ($q) => $q->where('unit_sekolah.id', $this->unit_id));
        }
        if ($this->jenis === 'pendidik') {
            $roster->guru();
        } elseif ($this->jenis === 'kependidikan') {
            $roster->nonGuru();
        }
        if ($this->search !== null && $this->search !== '') {
            $roster->where('nama_lengkap', 'like', '%'.$this->search.'%');
        }
        $rosterIds = $roster->pluck('id');

        // 2. Dedup passive vs active per pegawai per date.
        $byPegawai = $rows->groupBy('pegawai_id')->map(fn ($pegawaiRows) => self::dedupStatusPerTanggal($pegawaiRows));
        foreach ($rosterIds as $pegawaiId) {
            $byPegawai[$pegawaiId] ??= collect();
        }

        // 3. Build pegawai map (eager-load info).
        $pegawaiIds = $byPegawai->keys()->all();
        $pegawaiMap = Pegawai::whereIn('id', $pegawaiIds)
            ->with(['jabatans', 'units'])
            ->get()
            ->keyBy('id');

        // 4. Build holiday map for working days.
        $unitIds = array_unique(array_merge(
            $this->unit_id ? [$this->unit_id] : [],
            $rows->pluck('unit_sekolah_id')->filter()->unique()->all(),
            $pegawaiMap->flatMap(fn ($pegawai) => $pegawai->units->pluck('id'))->unique()->all(),
        ));
        $holidayMap = self::buildHolidayWeekdayMap($unitIds, $start, $end);

        // Status akhir pekan di luar hari kerja unit tidak dihitung. Record
        // pada hari libur tetap terlihat; hari libur dikeluarkan dari denominator.
        $byPegawai = $byPegawai->map(function ($deduped, $pegawaiId) use ($pegawaiMap) {
            $pegawai = $pegawaiMap->get($pegawaiId);
            $unit = $this->unit_id ? $pegawai?->units->firstWhere('id', $this->unit_id) : $pegawai?->unitUtama();
            $workingDays = $unit ? Pegawai::hariKerjaUnit($unit) : ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];

            return $deduped->filter(function ($presensi) use ($workingDays) {
                $date = Carbon::parse($presensi->tanggal);
                $day = $date->locale('id')->translatedFormat('l');

                return in_array($day, $workingDays, true);
            })->values();
        });

        // 5b. Range sakit: surat dokter vs lengkap (surat + tugas).
        $sakitRanges = BobotKehadiran::sakitRanges($byPegawai->keys()->all(), $start, $end);

        // 6. Assemble final rekap.
        $this->rekap = $byPegawai->map(function ($deduped, $pegawaiId) use ($pegawaiMap, $start, $end, $holidayMap, $sakitRanges) {
            $counts = $deduped->groupBy('status')->map(fn ($g) => $g->count())->all();
            $hadir = $counts['hadir'] ?? 0;
            $telat = $counts['telat'] ?? 0;
            $sakit = $counts['sakit'] ?? 0;
            $izin = $counts['izin'] ?? 0;
            $cuti = $counts['cuti'] ?? 0;

            $sakitDates = $deduped->where('status', 'sakit')
                ->map(fn ($p) => $p->tanggal instanceof \DateTimeInterface ? $p->tanggal->format('Y-m-d') : (string) $p->tanggal);
            $pegawai = $pegawaiMap->get($pegawaiId);
            $unit = $this->unit_id
                ? $pegawai?->units->firstWhere('id', $this->unit_id)
                : $pegawai?->unitUtama();
            $split = BobotKehadiran::splitSakit($sakitDates, $sakitRanges->get($pegawaiId, []), $unit?->sakitWajibTugas() ?? false);
            $hariKerja = self::computeUniformWorkingDays(
                $start,
                $end,
                $holidayMap,
                $unit?->id ? (int) $unit->id : null,
                $unit ? Pegawai::hariKerjaUnit($unit) : null,
            );
            $bobot = $unit?->bobotHadiran() ?? BobotKehadiran::DEFAULTS;
            $hadirEq = BobotKehadiran::hadirEq($hadir, $telat, $split['surat'], $split['tanpa'], $izin, 0, $cuti, $bobot);

            return [
                'pegawai' => $pegawai,
                'hadir' => $hadir,
                'telat' => $telat,
                'sakit' => $sakit,
                'sakit_surat' => $split['surat'],
                'sakit_tanpa_surat' => $split['tanpa'],
                'izin' => $izin,
                'cuti' => $cuti,
                // Alpa = hari kerja tanpa record (sama dengan rumus payroll):
                // termasuk pegawai yang tidak absen sama sekali, bukan hanya status alpa manual.
                'alpa' => max(0, $hariKerja - $hadir - $telat - $sakit - $izin - $cuti),
                'hariKerja' => $hariKerja,
                'persen' => BobotKehadiran::persenBerbobot($hadirEq, $hariKerja),
            ];
        })->sortBy(fn ($r) => mb_strtolower($r['pegawai']?->nama_lengkap ?? ''))->values();

        $this->applyStatusFilter();
    }

    /**
     * Filter status (opsional): tampilkan hanya pegawai yang mengalami status
     * terpilih pada periode. Alpa pakai rumus alpa (bukan status alpa manual).
     */
    private function applyStatusFilter(): void
    {
        if ($this->status === null || $this->status === '') {
            return;
        }

        $status = $this->status;
        $this->rekap = $this->rekap
            ->filter(fn ($r) => $status === 'alpa' ? $r['alpa'] > 0 : ($r[$status] ?? 0) > 0)
            ->values();
    }

    /**
     * Dedup: 1 hari = 1 status per pegawai (satu aturan untuk rekap,
     * rincian, dan matriks harian — jangan tulis logika lain).
     * Priority: hadir > telat > sakit > izin > cuti > alpa.
     */
    public static function dedupStatusPerTanggal(Collection $rows): Collection
    {
        $priority = ['hadir' => 0, 'telat' => 1, 'sakit' => 2, 'izin' => 3, 'cuti' => 4, 'alpa' => 5];

        return $rows
            ->groupBy(fn ($p) => $p->tanggal instanceof \DateTimeInterface ? $p->tanggal->format('Y-m-d') : (string) $p->tanggal)
            ->map(fn ($dateRows) => $dateRows->sortBy(fn ($p) => $priority[$p->status] ?? 9)->first())
            ->values();
    }

    /**
     * Working days sesuai hari operasi unit (default Senin–Jumat), dikurangi libur nasional/unit.
     */
    public static function computeUniformWorkingDays(Carbon $start, Carbon $end, array $holidayMap, ?int $unitId = null, ?array $workingDays = null): int
    {
        $hariMap = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $dayNumbers = array_flip($hariMap);
        $weekdays = $workingDays
            ? array_values(array_intersect_key($dayNumbers, array_flip($workingDays)))
            : [1, 2, 3, 4, 5];

        $totalDays = $start->diffInDays($end) + 1;
        $count = 0;

        foreach ($weekdays as $target) {
            $fullWeeks = intdiv($totalDays, 7);
            $remainderDays = $totalDays % 7;
            $hits = $fullWeeks;

            $startDayOfWeek = $start->dayOfWeek;
            for ($i = 0; $i < $remainderDays; $i++) {
                if ((($startDayOfWeek + $i) % 7) === $target) {
                    $hits++;
                }
            }

            $hari = $hariMap[$target];
            $exclude = (int) ($unitId !== null
                ? ($holidayMap['effective'][$unitId][$hari] ?? $holidayMap['national'][$hari] ?? 0)
                : ($holidayMap['national'][$hari] ?? 0));

            $count += max(0, $hits - $exclude);
        }

        return $count;
    }

    public static function buildHolidayWeekdayMap(array $unitIds, Carbon $start, Carbon $end): array
    {
        // whereDate (bukan whereBetween): aman utk DATE (prod MySQL) dan
        // DATETIME/TEXT (sqlite dev/test) — string '...-01' membuang tanggal batas.
        $rows = HariLibur::whereDate('tanggal', '>=', $start->toDateString())
            ->whereDate('tanggal', '<=', $end->toDateString())
            ->where(function ($q) use ($unitIds) {
                $q->whereNull('unit_sekolah_id');
                if ($unitIds) {
                    $q->orWhereIn('unit_sekolah_id', $unitIds);
                }
            })
            ->get(['tanggal', 'unit_sekolah_id']);

        $idMap = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $national = [];
        $units = [];
        $nationalDates = [];
        $unitDates = [];
        $dates = [];
        foreach ($rows as $h) {
            $wd = $idMap[(int) Carbon::parse($h->tanggal)->dayOfWeek];
            $date = Carbon::parse($h->tanggal)->toDateString();
            $dates[] = $date;
            if ($h->unit_sekolah_id === null) {
                $national[$wd] = ($national[$wd] ?? 0) + 1;
                $nationalDates[$date] = $wd;
            } else {
                $units[$h->unit_sekolah_id][$wd] = ($units[$h->unit_sekolah_id][$wd] ?? 0) + 1;
                $unitDates[$h->unit_sekolah_id][$date] = $wd;
            }
        }

        $effective = [];
        $unitHolidayDates = [];
        foreach ($unitIds as $unitId) {
            $unitHolidayDates[$unitId] = array_keys($nationalDates + ($unitDates[$unitId] ?? []));
            foreach (($nationalDates + ($unitDates[$unitId] ?? [])) as $weekday) {
                $effective[$unitId][$weekday] = ($effective[$unitId][$weekday] ?? 0) + 1;
            }
        }

        // 'dates' dipakai matriks harian untuk menandai sel libur per tanggal.
        return [
            'national' => $national,
            'units' => $units,
            'effective' => $effective,
            'national_dates' => $nationalDates,
            'unit_dates' => $unitDates,
            'unit_holiday_dates' => $unitHolidayDates,
            'dates' => array_values(array_unique($dates)),
        ];
    }

    public function collection(): Collection
    {
        return $this->rekap ?? collect();
    }

    /**
     * Summary data for frontend preview (beyond tabular).
     */
    public function summaryData(): array
    {
        $rekap = $this->rekap ?? collect();
        $total = $rekap->count();
        $avgPersen = $total > 0
            ? (int) round($rekap->sum(fn ($r) => $r['persen']) / $total)
            : 0;

        return [
            'totalPegawai' => $total,
            'avgKehadiran' => $avgPersen,
        ];
    }

    public function map($d): array
    {
        $hariKerja = $d['hariKerja'];
        $persen = $hariKerja > 0 ? min(100, $d['persen']) : 0;

        $pegawai = $d['pegawai'];

        return array_map([self::class, 'escapeFormula'], [
            $pegawai?->nama_lengkap ?? '-',
            $pegawai?->nuptk ?? '-',
            $pegawai ? $pegawai->jenisPegawaiLabel() : '-',
            $d['hadir'],
            $d['telat'],
            $d['sakit_surat'],
            $d['sakit_tanpa_surat'],
            $d['izin'],
            $d['cuti'],
            $d['alpa'],
            $hariKerja,
            $persen.'%',
        ]);
    }

    public function headings(): array
    {
        return [
            'Nama Pegawai',
            'NIP/Nuptk',
            'Jenis',
            'Hadir',
            'Telat',
            'Sakit Lengkap',
            'Sakit Belum Lengkap',
            'Izin',
            'Cuti',
            'Alpa',
            'Hari Kerja',
            '% Kehadiran',
        ];
    }

    public function startCell(): string
    {
        return 'A6';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = 'L';

                $kopUnit = $this->unit_id ? UnitSekolah::find($this->unit_id) : UnitSekolah::where('nama', 'like', 'Yayasan%')->first();
                $namaUnit = $kopUnit?->nama ?? 'Semua Unit Sekolah';
                $periodeStr = Carbon::parse($this->start_date)->format('d/m/Y').' s/d '.Carbon::parse($this->end_date)->format('d/m/Y');

                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->setCellValue('A1', strtoupper($kopUnit?->nama ?? config('yayasan.name')));
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->mergeCells("A2:{$lastCol}2");
                $sheet->setCellValue('A2', 'LAPORAN REKAPITULASI KEHADIRAN');
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->mergeCells("A3:{$lastCol}3");
                $sheet->setCellValue('A3', 'Periode: '.$periodeStr.' | Unit: '.$namaUnit);
                $sheet->getStyle('A3')->getFont()->setItalic(true);
                $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->mergeCells("A5:{$lastCol}5");
                $sheet->setCellValue('A5', '% Kehadiran berbobot = (Hadir + Telat + Sakit/Izin/Cuti berbobot per unit) / Hari Kerja × 100 — Sakit dengan surat dokter dibayar penuh (bobot 1.0 default); Alpa = Hari Kerja − (Hadir + Telat + Sakit + Izin + Cuti), termasuk hari tanpa record.');
                $sheet->getStyle('A5')->getFont()->setItalic(true)->setSize(9)->setColor(new Color('FF6B7280'));
                $sheet->getStyle('A5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                $sheet->getStyle("A6:{$lastCol}6")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FF0F3D3E'],
                    ],
                ]);
            },
        ];
    }
}
