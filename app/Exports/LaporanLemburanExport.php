<?php

namespace App\Exports;

use App\Models\PenggajianDetail;
use App\Models\UnitSekolah;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class LaporanLemburanExport implements FromCollection, ShouldAutoSize, WithCustomStartCell, WithEvents, WithHeadings, WithMapping
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
        $query = PenggajianDetail::with(['penggajian.pegawai.units'])
            ->whereHas('penggajian', function ($q) {
                $q->whereBetween('tanggal_generate', [$this->start_date, $this->end_date]);

                if ($this->unit_id) {
                    $q->whereHas('pegawai.units', function ($q2) {
                        $q2->where('unit_sekolah.id', $this->unit_id);
                    });
                }
            });

        return $query->get();
    }

    public function map($detail): array
    {
        $unitName = '-';
        if ($detail->penggajian && $detail->penggajian->pegawai && $detail->penggajian->pegawai->units->isNotEmpty()) {
            $unitName = $detail->penggajian->pegawai->units->first()->nama;
        }

        return [
            $detail->penggajian->pegawai->nik ?? '-',
            $detail->penggajian->pegawai->nama_lengkap ?? '-',
            $unitName,
            $detail->nama_komponen,
            ucfirst($detail->tipe),
            $detail->nominal,
        ];
    }

    public function headings(): array
    {
        return [
            'NIP',
            'Nama Pegawai',
            'Unit Sekolah',
            'Komponen Gaji',
            'Tipe',
            'Nominal (Rp)',
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

                $namaUnit = 'Semua Unit Sekolah';
                if ($this->unit_id) {
                    $unit = UnitSekolah::find($this->unit_id);
                    $namaUnit = $unit ? $unit->nama : 'Semua Unit Sekolah';
                }

                $periodeStr = Carbon::parse($this->start_date)->format('d/m/Y').' s/d '.Carbon::parse($this->end_date)->format('d/m/Y');

                // Kop Yayasan
                $sheet->mergeCells('A1:F1');
                $sheet->setCellValue('A1', 'YAYASAN PENDIDIKAN');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->mergeCells('A2:F2');
                $sheet->setCellValue('A2', 'LAPORAN DETAIL KOMPONEN GAJI (LEMBUR & POTONGAN)');
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->mergeCells('A3:F3');
                $sheet->setCellValue('A3', 'Periode: '.$periodeStr.' | Unit: '.$namaUnit);
                $sheet->getStyle('A3')->getFont()->setItalic(true);
                $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Styling for Headings
                $sheet->getStyle('A6:F6')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FFC9A227'], // Accent color
                    ],
                ]);

                // Format Columns as Currency
                $sheet->getStyle('F7:F1000')->getNumberFormat()->setFormatCode('"Rp "#,##0.00_-');
            },
        ];
    }
}
