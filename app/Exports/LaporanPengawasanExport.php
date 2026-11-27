<?php

namespace App\Exports;

use App\Services\LaporanPemenuhanExportRows;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LaporanPengawasanExport implements FromArray, WithColumnWidths, WithStyles
{
    public function __construct(private readonly array $data) {}

    public function array(): array
    {
        $period = $this->data['period'];
        $unit = $this->data['unit'];
        $rows = [];

        $rows[] = ['LAPORAN KEHADIRAN DAN PEMENUHAN TUGAS'];
        $rows[] = ['Yayasan Nuurul Muttaqin'];
        $rows[] = ['Unit', $unit['nama'] ?? 'Semua Unit'];
        $rows[] = ['Periode', $period['label']];
        $rows[] = ['Tahun Pelajaran', $period['tahun_ajaran'].' · Semester '.$period['semester']];
        $rows[] = [];
        $rows = array_merge($rows, app(LaporanPemenuhanExportRows::class)->build($this->data));
        $rows[] = ['KELAS & MENGAJAR'];
        $rows[] = ['Tanggal', 'Jam', 'Kelas', 'Mapel', 'Guru', 'Status', 'Sumber', 'Catatan'];

        foreach ($this->data['class_issues'] as $issue) {
            $rows[] = [
                $issue['tanggal'],
                $issue['jam'],
                $issue['kelas'],
                $issue['mapel'],
                $issue['guru'],
                $this->statusLabel($issue['status']),
                $issue['sumber'],
                $issue['catatan'],
            ];
        }

        $rows[] = [];
        $rows[] = ['Dicetak', $this->data['generated_at']];

        return $rows;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 28,
            'B' => 20,
            'C' => 14,
            'D' => 14,
            'E' => 14,
            'F' => 16,
            'G' => 16,
            'H' => 16,
            'I' => 12,
            'J' => 12,
            'K' => 60,
            'L' => 24,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
        ];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'belum_terpantau' => 'Belum terpantau',
            'kelas_kosong' => 'Kelas kosong',
            'perlu_cek' => 'Perlu cek',
            'digantikan' => 'Digantikan',
            'penugasan' => 'Penugasan',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    private function fmtJp(float|int $value): string
    {
        $value = round((float) $value, 2);

        return $value == floor($value)
            ? (string) (int) $value
            : number_format($value, 2, ',', '.');
    }
}
