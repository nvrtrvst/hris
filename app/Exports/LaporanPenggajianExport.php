<?php

namespace App\Exports;

use App\Models\Penggajian;
use App\Models\UnitSekolah;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Carbon\Carbon;

class LaporanPenggajianExport implements FromCollection, WithHeadings, WithMapping, WithEvents, WithCustomStartCell, ShouldAutoSize
{
    protected $start_date;
    protected $end_date;
    protected $unit_id;

    public function __construct($start_date, $end_date, $unit_id = null)
    {
        $this->start_date = $start_date;
        $this->end_date = $end_date;
        $this->unit_id = $unit_id;
    }

    public function collection()
    {
        $query = Penggajian::with(['pegawai.units'])
            ->whereBetween('tanggal_generate', [$this->start_date, $this->end_date]);

        if ($this->unit_id) {
            $query->whereHas('pegawai.units', function($q) {
                $q->where('unit_sekolah.id', $this->unit_id);
            });
        }

        return $query->get();
    }

    public function map($penggajian): array
    {
        $unitName = '-';
        if ($penggajian->pegawai && $penggajian->pegawai->units->isNotEmpty()) {
            $unitName = $penggajian->pegawai->units->first()->nama;
        }

        return [
            $penggajian->pegawai->nik ?? '-',
            $penggajian->pegawai->nama_lengkap ?? '-',
            $unitName,
            $penggajian->periode_bulan,
            $penggajian->total_pendapatan,
            $penggajian->total_potongan,
            $penggajian->gaji_bersih,
            ucfirst($penggajian->status)
        ];
    }

    public function headings(): array
    {
        return [
            'NIP',
            'Nama Pegawai',
            'Unit Sekolah',
            'Periode Penggajian',
            'Total Pendapatan (Rp)',
            'Total Potongan (Rp)',
            'Take Home Pay (Rp)',
            'Status'
        ];
    }

    public function startCell(): string
    {
        return 'A6';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                
                $namaUnit = 'Semua Unit Sekolah';
                if ($this->unit_id) {
                    $unit = UnitSekolah::find($this->unit_id);
                    $namaUnit = $unit ? $unit->nama : 'Semua Unit Sekolah';
                }

                $periodeStr = Carbon::parse($this->start_date)->format('d/m/Y') . ' s/d ' . Carbon::parse($this->end_date)->format('d/m/Y');

                // Kop Yayasan
                $sheet->mergeCells('A1:H1');
                $sheet->setCellValue('A1', 'YAYASAN PENDIDIKAN');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

                $sheet->mergeCells('A2:H2');
                $sheet->setCellValue('A2', 'LAPORAN REKAPITULASI PENGGAJIAN PEGAWAI');
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

                $sheet->mergeCells('A3:H3');
                $sheet->setCellValue('A3', 'Periode: ' . $periodeStr . ' | Unit: ' . $namaUnit);
                $sheet->getStyle('A3')->getFont()->setItalic(true);
                $sheet->getStyle('A3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

                // Styling for Headings
                $sheet->getStyle('A6:H6')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FF1C5D5E'] // Secondary color
                    ]
                ]);

                // Format Columns as Currency
                $sheet->getStyle('E7:G1000')->getNumberFormat()->setFormatCode('"Rp "#,##0.00_-');
            },
        ];
    }
}
