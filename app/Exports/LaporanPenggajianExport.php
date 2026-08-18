<?php

namespace App\Exports;

use App\Models\Penggajian;
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

class LaporanPenggajianExport implements FromCollection, ShouldAutoSize, WithCustomStartCell, WithEvents, WithHeadings, WithMapping
{
    protected $start_date;

    protected $end_date;

    protected $unit_id;

    protected $jenis;

    public function __construct($start_date, $end_date, $unit_id = null, $jenis = null)
    {
        $this->start_date = $start_date;
        $this->end_date = $end_date;
        $this->unit_id = $unit_id;
        $this->jenis = $jenis;
    }

    public function collection()
    {
        // Eager-load jabatans untuk kolom Jenis (hindari N+1 di map()).
        $query = Penggajian::with(['pegawai.units', 'pegawai.jabatans']);

        $months = [];
        $cursor = Carbon::parse($this->start_date)->startOfMonth();
        $end = Carbon::parse($this->end_date)->startOfMonth();
        while ($cursor->lte($end)) {
            $months[] = $cursor->format('m-Y');
            $cursor->addMonth();
        }
        $query->whereIn('periode_bulan', $months);

        if ($this->unit_id) {
            $query->whereHas('pegawai.units', function ($q) {
                $q->where('unit_sekolah.id', $this->unit_id);
            });
        }

        if ($this->jenis === 'pendidik') {
            $query->whereHas('pegawai', fn ($q) => $q->guru());
        } elseif ($this->jenis === 'kependidikan') {
            $query->whereHas('pegawai', fn ($q) => $q->nonGuru());
        }

        return $query->get();
    }

    public function map($penggajian): array
    {
        $unitName = '-';
        if ($penggajian->pegawai && $penggajian->pegawai->units->isNotEmpty()) {
            $unitName = $penggajian->pegawai->units->first()->nama;
        }

        $pegawai = $penggajian->pegawai;

        return [
            $pegawai->nik ?? '-',
            $pegawai->nama_lengkap ?? '-',
            $pegawai ? $pegawai->jenisPegawaiLabel() : '-',
            $unitName,
            $penggajian->periode_bulan,
            $penggajian->total_pendapatan,
            $penggajian->total_potongan,
            $penggajian->gaji_bersih,
            ucfirst($penggajian->status),
        ];
    }

    public function headings(): array
    {
        return [
            'NIP',
            'Nama Pegawai',
            'Jenis',
            'Unit Sekolah',
            'Periode Penggajian',
            'Total Pendapatan (Rp)',
            'Total Potongan (Rp)',
            'Take Home Pay (Rp)',
            'Status',
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
                $sheet->mergeCells('A1:I1');
                $sheet->setCellValue('A1', 'YAYASAN PENDIDIKAN');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->mergeCells('A2:I2');
                $sheet->setCellValue('A2', 'LAPORAN REKAPITULASI PENGGAJIAN PEGAWAI');
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->mergeCells('A3:I3');
                $sheet->setCellValue('A3', 'Periode: '.$periodeStr.' | Unit: '.$namaUnit);
                $sheet->getStyle('A3')->getFont()->setItalic(true);
                $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Styling for Headings
                $sheet->getStyle('A6:I6')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FF1C5D5E'], // Secondary color
                    ],
                ]);

                // Format Columns as Currency (F=Total Pendapatan, G=Potongan, H=Take Home Pay)
                $sheet->getStyle('F7:H1000')->getNumberFormat()->setFormatCode('"Rp "#,##0.00_-');
            },
        ];
    }
}
