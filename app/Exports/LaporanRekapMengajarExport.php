<?php

namespace App\Exports;

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

/**
 * Rekap presensi mengajar per guru: total periode + breakdown per minggu.
 * Sumber data = presensi per JP (cron finalize-alpa melengkapi JP yang tak
 * di-slide sebagai alpa), jadi terjadwal = total row per jadwal.
 */
class LaporanRekapMengajarExport implements FromCollection, ShouldAutoSize, WithCustomStartCell, WithEvents, WithHeadings, WithMapping
{
    protected $start_date;

    protected $end_date;

    protected $unit_id;

    protected $jenis;

    protected $weeks = [];

    public function __construct($start_date, $end_date, $unit_id = null, $jenis = null)
    {
        $this->start_date = $start_date;
        $this->end_date = $end_date;
        $this->unit_id = $unit_id;
        $this->jenis = $jenis;

        // Minggu dihitung di constructor (bukan collection) supaya headings()
        // — yang dipanggil Maatwebsite SEBELUM iterasi collection — sudah
        // punya daftar kolom minggu.
        $start = Carbon::parse($start_date);
        $end = Carbon::parse($end_date);
        $cursor = $start->copy()->startOfWeek(Carbon::MONDAY);
        while ($cursor <= $end) {
            $weekStart = $cursor->copy()->max($start);
            $weekEnd = $cursor->copy()->addDays(4)->min($end);
            $this->weeks[] = [
                'label' => 'M'.$weekStart->format('d/m'),
                'start' => $weekStart->toDateString(),
                'end' => $weekEnd->toDateString(),
            ];
            $cursor->addWeek();
        }
    }

    public function collection(): Collection
    {
        $weeks = $this->weeks;
        $start = Carbon::parse($this->start_date);
        $end = Carbon::parse($this->end_date);

        $query = Presensi::with(['pegawai.jabatans', 'pegawai.mapels', 'jadwal'])
            ->whereBetween('tanggal', [$this->start_date, $this->end_date])
            ->whereNotNull('jadwal_id')
            ->where('tipe_presensi', 'mengajar');

        if ($this->unit_id) {
            $query->where('unit_sekolah_id', $this->unit_id);
        }

        if ($this->jenis === 'pendidik') {
            $query->whereHas('pegawai', fn ($q) => $q->guru());
        } elseif ($this->jenis === 'kependidikan') {
            $query->whereHas('pegawai', fn ($q) => $q->nonGuru());
        }

        $rows = $query->get();

        // Agregasi per guru + per minggu: JP terjadwal (total row), hadir,
        // telat, alpa. Izin/sakit/cuti pada JP mengajar jarang (blok hari
        // penuh) — tetap dihitung sebagai tidak hadir mengajar.
        $byPegawai = [];
        foreach ($rows as $p) {
            $pid = $p->pegawai_id;
            if (! isset($byPegawai[$pid])) {
                $byPegawai[$pid] = [
                    'pegawai' => $p->pegawai,
                    'total' => ['terjadwal' => 0, 'hadir' => 0, 'telat' => 0, 'alpa' => 0],
                    'minggu' => [],
                ];
            }
            $d = &$byPegawai[$pid];

            $d['total']['terjadwal']++;
            if ($p->status === 'telat') {
                $d['total']['telat']++;
            } elseif ($p->status === 'hadir') {
                $d['total']['hadir']++;
            } elseif ($p->status === 'alpa') {
                $d['total']['alpa']++;
            }

            $tanggal = $p->tanggal->toDateString();
            foreach ($weeks as $i => $w) {
                if ($tanggal >= $w['start'] && $tanggal <= $w['end']) {
                    if (! isset($d['minggu'][$i])) {
                        $d['minggu'][$i] = ['terjadwal' => 0, 'hadir' => 0, 'telat' => 0, 'alpa' => 0];
                    }
                    $d['minggu'][$i]['terjadwal']++;
                    if ($p->status === 'telat') {
                        $d['minggu'][$i]['telat']++;
                    } elseif ($p->status === 'hadir') {
                        $d['minggu'][$i]['hadir']++;
                    } elseif ($p->status === 'alpa') {
                        $d['minggu'][$i]['alpa']++;
                    }
                    break;
                }
            }
        }

        return collect(array_values($byPegawai))->sortBy(fn ($d) => $d['pegawai']->nama_lengkap ?? '')->values();
    }

    public function map($d): array
    {
        $hadirPersen = $d['total']['terjadwal'] > 0
            ? round((($d['total']['hadir'] + $d['total']['telat']) / $d['total']['terjadwal']) * 100)
            : 0;

        $row = [
            $d['pegawai']->nama_lengkap ?? '-',
            $d['pegawai']->nip ?? '-',
            $d['pegawai'] ? $d['pegawai']->jenisPegawaiLabel() : '-',
            $d['pegawai']->mapels->pluck('nama')->unique()->implode(', ') ?: '-',
            $d['total']['terjadwal'],
            $d['total']['hadir'],
            $d['total']['telat'],
            $d['total']['alpa'],
            $hadirPersen.'%',
        ];

        // Kolom mingguan: "hadir/terjadwal (telat t)" — telat terlihat eksplisit.
        foreach ($this->weeks as $i => $w) {
            $m = $d['minggu'][$i] ?? null;
            $row[] = $m
                ? sprintf('%d/%d (telat %d)', $m['hadir'] + $m['telat'], $m['terjadwal'], $m['telat'])
                : '-';
        }

        return $row;
    }

    public function headings(): array
    {
        $heads = [
            'Nama Guru',
            'NIP',
            'Jenis',
            'Mata Pelajaran',
            'JP Terjadwal',
            'Hadir',
            'Telat',
            'Alpa',
            '% Kehadiran',
        ];

        foreach ($this->weeks ?? [] as $w) {
            $heads[] = $w['label'];
        }

        return $heads;
    }

    public function startCell(): string
    {
        return 'A6';
    }

    public function registerEvents(): array
    {
        $lastCol = chr(ord('A') + 8 + count($this->weeks ?? []));

        return [
            AfterSheet::class => function (AfterSheet $event) use ($lastCol) {
                $sheet = $event->sheet->getDelegate();

                $namaUnit = 'Semua Unit Sekolah';
                if ($this->unit_id) {
                    $unit = UnitSekolah::find($this->unit_id);
                    $namaUnit = $unit ? $unit->nama : 'Semua Unit Sekolah';
                }

                $periodeStr = Carbon::parse($this->start_date)->format('d/m/Y').' s/d '.Carbon::parse($this->end_date)->format('d/m/Y');

                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->setCellValue('A1', 'YAYASAN PENDIDIKAN');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->mergeCells("A2:{$lastCol}2");
                $sheet->setCellValue('A2', 'LAPORAN REKAPITULASI PRESENSI MENGAJAR');
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
