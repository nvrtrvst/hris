<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class LaporanKetidakhadiranPiketExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    use FormulaEscapable;

    /** @param list<array<string, mixed>> $rows */
    public function __construct(private array $rows) {}

    public function collection()
    {
        return collect($this->rows);
    }

    public function headings(): array
    {
        return [
            'Tanggal',
            'Pegawai',
            'Unit',
            'Jam Piket',
            'Area',
            'Presensi',
            'Status Laporan',
            'Keterangan',
        ];
    }

    public function map($row): array
    {
        $kategori = $row['kategori'] === 'terlambat_lapor' ? 'Terlambat lapor' : 'Alpa piket';
        $keterangan = $row['kategori'] === 'terlambat_lapor'
            ? 'Terkirim pukul '.($row['submitted_at'] ?? '-')
            : 'Belum terkirim';

        return [
            $row['tanggal'],
            self::escapeFormula($row['pegawai']['nama_lengkap'] ?? '-'),
            self::escapeFormula($row['unit'] ?? '-'),
            $row['jam'],
            self::escapeFormula($row['area'] ?? '-'),
            ucfirst($row['presensi_status']).' '.$row['presensi_jam'],
            $kategori,
            $keterangan,
        ];
    }
}
