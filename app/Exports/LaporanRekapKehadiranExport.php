<?php

namespace App\Exports;

use App\Models\HariLibur;
use App\Models\Jadwal;
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
use PhpOffice\PhpSpreadsheet\Style\Fill;

class LaporanRekapKehadiranExport implements FromCollection, ShouldAutoSize, WithCustomStartCell, WithEvents, WithHeadings, WithMapping
{
    use FormulaEscapable;

    protected $start_date;

    protected $end_date;

    protected $unit_id;

    protected $jenis;

    /** @var Collection<int, array{pegawai: Pegawai, hadir: int, telat: int, sakit: int, izin: int, cuti: int, alpa: int, hariKerja: int}> */
    protected $rekap;

    public function __construct($start_date, $end_date, $unit_id = null, $jenis = null)
    {
        $this->start_date = $start_date;
        $this->end_date = $end_date;
        $this->unit_id = $unit_id;
        $this->jenis = $jenis;

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

        $rows = $query->get(['pegawai_id', 'tanggal', 'status', 'jadwal_id', 'unit_sekolah_id']);

        // 2. Dedup passive vs active per pegawai per date.
        $byPegawai = $rows->groupBy('pegawai_id')->map(fn ($pegawaiRows) => $this->dedupPassiveByActiveDate($pegawaiRows));

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
        ));
        $holidayMap = $this->buildHolidayWeekdayMap($unitIds, $start, $end);

        // 5. Compute working days per pegawai.
        $workingDays = $this->computeWorkingDays($pegawaiIds, $start, $end, $holidayMap);

        // 6. Assemble final rekap.
        $this->rekap = $byPegawai->map(function ($deduped, $pegawaiId) use ($pegawaiMap, $workingDays) {
            $counts = $deduped->groupBy('status')->map(fn ($g) => $g->count())->all();

            return [
                'pegawai' => $pegawaiMap->get($pegawaiId),
                'hadir' => $counts['hadir'] ?? 0,
                'telat' => $counts['telat'] ?? 0,
                'sakit' => $counts['sakit'] ?? 0,
                'izin' => $counts['izin'] ?? 0,
                'cuti' => $counts['cuti'] ?? 0,
                'alpa' => $counts['alpa'] ?? 0,
                'hariKerja' => $workingDays[$pegawaiId] ?? 0,
            ];
        })->values();
    }

    /**
     * Dedup: 1 hari = 1 status. Active (hadir/telat) wins over passive.
     */
    private function dedupPassiveByActiveDate(Collection $rows): Collection
    {
        $activeDates = $rows
            ->filter(fn ($p) => in_array($p->status, ['hadir', 'telat'], true))
            ->pluck('tanggal')
            ->map(fn ($t) => $t instanceof \DateTimeInterface ? $t->format('Y-m-d') : (string) $t)
            ->flip();

        return $rows->filter(fn ($p) => in_array($p->status, ['hadir', 'telat'], true)
            || ! $activeDates->has($p->tanggal instanceof \DateTimeInterface ? $p->tanggal->format('Y-m-d') : (string) $p->tanggal));
    }

    /**
     * Compute working days per pegawai using jadwal × countWeekdayInRange − holidays.
     */
    private function computeWorkingDays(array $pegawaiIds, Carbon $start, Carbon $end, array $holidayMap): array
    {
        $jadwals = Jadwal::whereIn('pegawai_id', $pegawaiIds)
            ->select('pegawai_id', 'hari', 'unit_sekolah_id')
            ->get()
            ->groupBy('pegawai_id');

        $hariMap = [
            'Minggu' => 0, 'Senin' => 1, 'Selasa' => 2, 'Rabu' => 3,
            'Kamis' => 4, 'Jumat' => 5, 'Sabtu' => 6,
        ];

        $result = [];
        foreach ($jadwals as $pid => $jadwalList) {
            $total = 0;
            foreach ($jadwalList as $j) {
                $target = $hariMap[$j->hari] ?? null;
                if ($target === null || $start->gt($end)) {
                    continue;
                }

                $totalDays = $start->diffInDays($end) + 1;
                $fullWeeks = intdiv($totalDays, 7);
                $remainderDays = $totalDays % 7;
                $count = $fullWeeks;

                $startDayOfWeek = $start->dayOfWeek;
                for ($i = 0; $i < $remainderDays; $i++) {
                    if ((($startDayOfWeek + $i) % 7) === $target) {
                        $count++;
                    }
                }

                $exclude = (int) ($holidayMap['national'][$j->hari] ?? 0)
                    + (int) ($holidayMap['units'][$j->unit_sekolah_id][$j->hari] ?? 0);

                $total += max(0, $count - $exclude);
            }
            $result[$pid] = $total;
        }

        return $result;
    }

    private function buildHolidayWeekdayMap(array $unitIds, Carbon $start, Carbon $end): array
    {
        $rows = HariLibur::whereBetween('tanggal', [$start->toDateString(), $end->toDateString()])
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
        foreach ($rows as $h) {
            $wd = $idMap[(int) Carbon::parse($h->tanggal)->dayOfWeek];
            if ($h->unit_sekolah_id === null) {
                $national[$wd] = ($national[$wd] ?? 0) + 1;
            } else {
                $units[$h->unit_sekolah_id][$wd] = ($units[$h->unit_sekolah_id][$wd] ?? 0) + 1;
            }
        }

        return ['national' => $national, 'units' => $units];
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
            ? round($rekap->sum(fn ($r) => $r['hariKerja'] > 0 ? (($r['hadir'] + $r['telat']) / $r['hariKerja']) * 100 : 0) / $total)
            : 0;

        return [
            'totalPegawai' => $total,
            'avgKehadiran' => $avgPersen,
        ];
    }

    public function map($d): array
    {
        $hariKerja = $d['hariKerja'];
        $persen = $hariKerja > 0
            ? round((($d['hadir'] + $d['telat']) / $hariKerja) * 100)
            : 0;

        $pegawai = $d['pegawai'];

        return array_map([self::class, 'escapeFormula'], [
            $pegawai?->nama_lengkap ?? '-',
            $pegawai?->nuptk ?? '-',
            $pegawai ? $pegawai->jenisPegawaiLabel() : '-',
            $d['hadir'],
            $d['telat'],
            $d['sakit'],
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
            'Sakit',
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
                $lastCol = 'K';

                $kopUnit = $this->unit_id ? UnitSekolah::find($this->unit_id) : UnitSekolah::where('nama', 'like', 'Yayasan%')->first();
                $namaUnit = $kopUnit?->nama ?? 'Semua Unit Sekolah';
                $periodeStr = Carbon::parse($this->start_date)->format('d/m/Y').' s/d '.Carbon::parse($this->end_date)->format('d/m/Y');

                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->setCellValue('A1', strtoupper($kopUnit?->nama ?? config('yayasan.name')));
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->mergeCells("A2:{$lastCol}2");
                $sheet->setCellValue('A2', 'LAPORAN REKAPITULASI KEHADIRAN PEGAWAI');
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->mergeCells("A3:{$lastCol}3");
                $sheet->setCellValue('A3', 'Periode: '.$periodeStr.' | Unit: '.$namaUnit);
                $sheet->getStyle('A3')->getFont()->setItalic(true);
                $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

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
