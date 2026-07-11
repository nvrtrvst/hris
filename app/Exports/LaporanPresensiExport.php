<?php

namespace App\Exports;

use App\Models\Presensi;
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

class LaporanPresensiExport implements FromCollection, ShouldAutoSize, WithCustomStartCell, WithEvents, WithHeadings, WithMapping
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
        $query = Presensi::with(['pegawai', 'unitSekolah'])
            ->whereBetween('tanggal', [$this->start_date, $this->end_date]);

        if ($this->unit_id) {
            $query->where('unit_sekolah_id', $this->unit_id);
        }

        return $query->orderBy('tanggal', 'asc')->get();
    }

    public function map($presensi): array
    {
        return [
            $presensi->tanggal->format('d/m/Y'),
            $presensi->pegawai->nama_lengkap ?? '-',
            $presensi->pegawai->nik ?? '-',
            $presensi->unitSekolah->nama ?? '-',
            $presensi->jam_masuk ?? '-',
            $presensi->jam_keluar ?? '-',
            ucfirst($presensi->status),
            $presensi->keterangan ?? '-',
        ];
    }

    public function headings(): array
    {
        return [
            'Tanggal',
            'Nama Pegawai',
            'NIP',
            'Unit Sekolah',
            'Jam Masuk',
            'Jam Keluar',
            'Status',
            'Keterangan',
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
                $sheet->mergeCells('A1:H1');
                $sheet->setCellValue('A1', 'YAYASAN PENDIDIKAN'); // Ganti dengan nama yayasan asli
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->mergeCells('A2:H2');
                $sheet->setCellValue('A2', 'LAPORAN REKAPITULASI PRESENSI PEGAWAI');
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->mergeCells('A3:H3');
                $sheet->setCellValue('A3', 'Periode: '.$periodeStr.' | Unit: '.$namaUnit);
                $sheet->getStyle('A3')->getFont()->setItalic(true);
                $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Styling for Headings (A6:H6)
                $sheet->getStyle('A6:H6')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FF0F3D3E'], // Primary color Yayasan
                    ],
                ]);
            },
        ];
    }
}
