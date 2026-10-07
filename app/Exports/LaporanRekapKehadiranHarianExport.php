<?php

namespace App\Exports;

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
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Rekapitulasi kehadiran harian (matriks): 1 pegawai = 1 baris,
 * 1 kolom per tanggal. Sel = 1 status per hari, dedup priority sama
 * dengan rekap agregat (LaporanRekapKehadiranExport::dedupStatusPerTanggal).
 * Rentang maksimal 31 hari — dijaga LaporanGenerateRequest.
 */
class LaporanRekapKehadiranHarianExport implements FromCollection, ShouldAutoSize, WithCustomStartCell, WithEvents, WithHeadings, WithMapping
{
    use FormulaEscapable;

    /** Satu huruf per status (sel matriks). */
    private const LETTERS = ['hadir' => 'H', 'telat' => 'T', 'sakit' => 'S', 'izin' => 'I', 'cuti' => 'C', 'alpa' => 'A'];

    /** Warna fill Excel per huruf (soft, agar mudah discan). */
    private const FILLS = [
        'H' => 'FFD1FAE5',
        'T' => 'FFFEF3C7',
        'S' => 'FFE0E7FF',
        'I' => 'FFE0F2FE',
        'C' => 'FFEDE9FE',
        'A' => 'FFFEE2E2',
        'L' => 'FFF2F4F7',
    ];

    protected $start_date;

    protected $end_date;

    protected $unit_id;

    protected $jenis;

    protected $search;

    protected $status;

    /** @var string[] Daftar tanggal periode (semua hari, termasuk weekend). */
    protected $dates = [];

    /** @var Collection<int, array{pegawai: Pegawai, cells: array<string, string>, hadir: int, telat: int, sakit: int, izin: int, cuti: int, alpa: int, hariKerja: int}> */
    protected $rekap;

    public function __construct($start_date, $end_date, $unit_id = null, $jenis = null, $search = null, $status = null)
    {
        $this->start_date = $start_date;
        $this->end_date = $end_date;
        $this->unit_id = $unit_id;
        $this->jenis = $jenis;
        $this->search = $search;
        $this->status = $status;

        $cursor = Carbon::parse($start_date);
        $end = Carbon::parse($end_date);
        while ($cursor <= $end) {
            $this->dates[] = $cursor->toDateString();
            $cursor->addDay();
        }

        $this->buildMatrix();
    }

    private function buildMatrix(): void
    {
        $start = Carbon::parse($this->start_date);
        $end = Carbon::parse($this->end_date);

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

        // 1 pegawai per tanggal = 1 status (aturan sama dengan rekap agregat).
        $byPegawai = $rows->groupBy('pegawai_id')->map(fn ($pegawaiRows) => LaporanRekapKehadiranExport::dedupStatusPerTanggal($pegawaiRows));

        $pegawaiMap = Pegawai::whereIn('id', $byPegawai->keys()->all())
            ->with(['jabatans', 'units'])
            ->get()
            ->keyBy('id');

        $unitIds = array_unique(array_merge(
            $this->unit_id ? [$this->unit_id] : [],
            $rows->pluck('unit_sekolah_id')->filter()->unique()->all(),
        ));
        $holidayMap = LaporanRekapKehadiranExport::buildHolidayWeekdayMap($unitIds, $start, $end);
        $hariKerja = LaporanRekapKehadiranExport::computeUniformWorkingDays($start, $end, $holidayMap);
        $holidayDates = array_flip($holidayMap['dates'] ?? []);
        $dates = $this->dates;

        $this->rekap = $byPegawai->map(function ($deduped, $pegawaiId) use ($pegawaiMap, $hariKerja, $holidayDates, $dates) {
            $cells = [];
            $counts = ['hadir' => 0, 'telat' => 0, 'sakit' => 0, 'izin' => 0, 'cuti' => 0, 'alpa' => 0];

            foreach ($deduped as $p) {
                $tanggal = $p->tanggal instanceof \DateTimeInterface ? $p->tanggal->format('Y-m-d') : (string) $p->tanggal;
                $cells[$tanggal] = self::LETTERS[$p->status] ?? '-';
                if (isset($counts[$p->status])) {
                    $counts[$p->status]++;
                }
            }

            // Sel kosong: hari libur/akhir pekan = 'L', hari kerja tanpa record = 'A'
            // (alpa, sejalan dengan rumus rekap agregat & payroll).
            foreach ($dates as $ds) {
                if (isset($cells[$ds])) {
                    continue;
                }
                $cells[$ds] = (Carbon::parse($ds)->isWeekend() || isset($holidayDates[$ds])) ? 'L' : 'A';
            }

            return [
                'pegawai' => $pegawaiMap->get($pegawaiId),
                'cells' => $cells,
                'hadir' => $counts['hadir'],
                'telat' => $counts['telat'],
                'sakit' => $counts['sakit'],
                'izin' => $counts['izin'],
                'cuti' => $counts['cuti'],
                'alpa' => max(0, $hariKerja - $counts['hadir'] - $counts['telat'] - $counts['sakit'] - $counts['izin'] - $counts['cuti']),
                'hariKerja' => $hariKerja,
            ];
        })->sortBy(fn ($r) => mb_strtolower($r['pegawai']?->nama_lengkap ?? ''))->values();

        if ($this->status !== null && $this->status !== '') {
            $status = $this->status;
            // Sama dengan rekap agregat: alpa = rumus alpa, bukan status manual.
            $this->rekap = $this->rekap
                ->filter(fn ($r) => $status === 'alpa' ? $r['alpa'] > 0 : ($r[$status] ?? 0) > 0)
                ->values();
        }
    }

    public function collection(): Collection
    {
        return $this->rekap ?? collect();
    }

    /**
     * Data matriks untuk grid FE: tanggal + sel per pegawai.
     */
    public function matrixData(Collection $rows): array
    {
        return [
            'dates' => $this->dates,
            'rows' => $rows->map(fn ($d) => [
                'id' => $d['pegawai']?->id,
                'nama' => $d['pegawai']?->nama_lengkap ?? '-',
                'cells' => $d['cells'],
            ])->values()->all(),
        ];
    }

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

        $row = [
            $pegawai?->nama_lengkap ?? '-',
            $pegawai?->nuptk ?? '-',
            $pegawai ? $pegawai->jenisPegawaiLabel() : '-',
        ];
        foreach ($this->dates as $ds) {
            $row[] = $d['cells'][$ds] ?? '-';
        }
        $row[] = $d['hadir'];
        $row[] = $d['telat'];
        $row[] = $d['sakit'];
        $row[] = $d['izin'];
        $row[] = $d['cuti'];
        $row[] = $d['alpa'];
        $row[] = $hariKerja;
        $row[] = $persen.'%';

        return array_map([self::class, 'escapeFormula'], $row);
    }

    public function headings(): array
    {
        $heads = ['Nama Pegawai', 'NIP/Nuptk', 'Jenis'];
        foreach ($this->dates as $ds) {
            $heads[] = Carbon::parse($ds)->format('d/m');
        }

        return array_merge($heads, [
            'Hadir', 'Telat', 'Sakit', 'Izin', 'Cuti', 'Alpa',
            'Hari Kerja', '% Kehadiran',
        ]);
    }

    public function startCell(): string
    {
        return 'A6';
    }

    public function registerEvents(): array
    {
        $dates = $this->dates;
        $rekap = $this->rekap ?? collect();
        $dateCount = count($dates);
        $lastCol = Coordinate::stringFromColumnIndex(3 + $dateCount + 8);

        return [
            AfterSheet::class => function (AfterSheet $event) use ($lastCol, $dates, $rekap, $dateCount) {
                $sheet = $event->sheet->getDelegate();

                $kopUnit = $this->unit_id ? UnitSekolah::find($this->unit_id) : UnitSekolah::where('nama', 'like', 'Yayasan%')->first();
                $namaUnit = $kopUnit?->nama ?? 'Semua Unit Sekolah';
                $periodeStr = Carbon::parse($this->start_date)->format('d/m/Y').' s/d '.Carbon::parse($this->end_date)->format('d/m/Y');

                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->setCellValue('A1', strtoupper($kopUnit?->nama ?? config('yayasan.name')));
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->mergeCells("A2:{$lastCol}2");
                $sheet->setCellValue('A2', 'LAPORAN REKAPITULASI KEHADIRAN HARIAN');
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->mergeCells("A3:{$lastCol}3");
                $sheet->setCellValue('A3', 'Periode: '.$periodeStr.' | Unit: '.$namaUnit);
                $sheet->getStyle('A3')->getFont()->setItalic(true);
                $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->mergeCells("A5:{$lastCol}5");
                $sheet->setCellValue('A5', 'Sel = status 1 pegawai per tanggal (H Hadir, T Telat, S Sakit, I Izin, C Cuti, A Alpa/Tidak Hadir, L Libur). % Kehadiran = (Hadir + Telat) / Hari Kerja × 100.');
                $sheet->getStyle('A5')->getFont()->setSize(9)->setColor(new Color('FF6B7280'));
                $sheet->getStyle('A5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                $sheet->getStyle("A6:{$lastCol}6")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FF0F3D3E'],
                    ],
                ]);

                // Freeze nama kolom + header (grid lebar).
                $sheet->freezePane('B7');

                // Warna sel per status.
                foreach ($rekap as $i => $d) {
                    $excelRow = 7 + $i;
                    foreach ($dates as $j => $ds) {
                        $letter = $d['cells'][$ds] ?? null;
                        if ($letter !== null && isset(self::FILLS[$letter])) {
                            $coord = Coordinate::stringFromColumnIndex(4 + $j).$excelRow;
                            $sheet->getStyle($coord)->getFill()
                                ->setFillType(Fill::FILL_SOLID)
                                ->setStartColor(new Color(self::FILLS[$letter]));
                        }
                    }
                }

                // Footer: % (H+T) per tanggal terhadap pegawai yang terekam.
                if ($rekap->count() > 0 && $dateCount > 0) {
                    $footerRow = 7 + $rekap->count();
                    $sheet->setCellValue("A{$footerRow}", '% Hadir (H+T) per tanggal');
                    $sheet->getStyle("A{$footerRow}")->getFont()->setBold(true)->setItalic(true);
                    foreach ($dates as $j => $ds) {
                        $terekam = 0;
                        $present = 0;
                        foreach ($rekap as $d) {
                            $letter = $d['cells'][$ds] ?? '-';
                            if ($letter === '-' || $letter === 'L') {
                                continue;
                            }
                            $terekam++;
                            if ($letter === 'H' || $letter === 'T') {
                                $present++;
                            }
                        }
                        $coord = Coordinate::stringFromColumnIndex(4 + $j).$footerRow;
                        $sheet->setCellValue($coord, $terekam > 0 ? round($present / $terekam * 100).'%' : '-');
                        $sheet->getStyle($coord)->getFont()->setBold(true)->setItalic(true);
                    }
                }
            },
        ];
    }
}
