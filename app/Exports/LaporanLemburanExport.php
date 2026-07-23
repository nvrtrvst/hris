<?php

namespace App\Exports;

use App\Helpers\FileHelper;
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
use PhpOffice\PhpSpreadsheet\Style\Color;
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
        $query = Presensi::with(['pegawai.units'])
            ->where('is_lembur', true)
            ->where('lembur_status', 'disetujui')
            ->whereNotNull('jam_masuk')
            ->whereNotNull('jam_keluar')
            ->whereBetween('tanggal', [$this->start_date, $this->end_date]);

        if ($this->unit_id) {
            $query->whereHas('pegawai.units', function ($q) {
                $q->where('unit_sekolah.id', $this->unit_id);
            });
        }

        return $query->orderBy('tanggal')->get();
    }

    public function map($presensi): array
    {
        $pegawai = $presensi->pegawai;
        $unitName = $pegawai && $pegawai->units->isNotEmpty()
            ? $pegawai->units->first()->nama
            : '-';

        $jamMulai = Carbon::parse($presensi->jam_masuk);
        $jamSelesai = Carbon::parse($presensi->jam_keluar);
        $totalMinutes = $jamMulai->diffInMinutes($jamSelesai);
        $totalHours = round($totalMinutes / 60, 2);

        return [
            $pegawai->nik ?? '-',
            $pegawai->nama_lengkap ?? '-',
            $unitName,
            $presensi->tanggal->format('d/m/Y'),
            $jamMulai->format('H:i'),
            $jamSelesai->format('H:i'),
            $totalHours,
            FileHelper::fotoUrl($presensi->foto_masuk),
        ];
    }

    public function headings(): array
    {
        return [
            'NIK',
            'Nama Pegawai',
            'Unit Sekolah',
            'Tanggal',
            'Jam Mulai',
            'Jam Selesai',
            'Total Jam',
            'Foto Bukti',
        ];
    }

    public function startCell(): string
    {
        return 'A7';
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
                $sheet->setCellValue('A1', 'YAYASAN PENDIDIKAN');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->mergeCells('A2:H2');
                $sheet->setCellValue('A2', 'LAPORAN LEMBUR');
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->mergeCells('A3:H3');
                $sheet->setCellValue('A3', 'Periode: '.$periodeStr.' | Unit: '.$namaUnit);
                $sheet->getStyle('A3')->getFont()->setItalic(true);
                $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->mergeCells('A4:H4');
                $sheet->setCellValue('A4', '*Hanya menampilkan lembur yang sudah DISETUJUI');
                $sheet->getStyle('A4')->getFont()->setItalic(true)->setColor(new Color('FF6B7280'));

                // Styling for Headings
                $sheet->getStyle('A7:H7')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FFC9A227'],
                    ],
                ]);

                // Wrap + link foto
                $sheet->getStyle('H8:H1000')->getNumberFormat()->setFormatCode('@');
            },
        ];
    }
}
