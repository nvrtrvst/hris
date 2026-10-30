<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LaporanPengawasanExport implements FromArray, WithColumnWidths, WithStyles
{
    public function __construct(private readonly array $data) {}

    public function array(): array
    {
        $summary = $this->data['summary'];
        $period = $this->data['period'];
        $unit = $this->data['unit'];
        $rows = [];

        $rows[] = ['LAPORAN PENGAWASAN KEHADIRAN & MENGAJAR'];
        $rows[] = ['Yayasan Nuurul Muttaqin'];
        $rows[] = ['Unit', $unit['nama'] ?? 'Semua Unit'];
        $rows[] = ['Periode', $period['label']];
        $rows[] = ['Tahun Pelajaran', $period['tahun_ajaran'].' · Semester '.$period['semester']];
        $rows[] = [];
        $rows[] = ['RINGKASAN'];
        $rows[] = ['Pegawai', 'Hari Pegawai', 'Hadir (hari)', 'Telat (hari)', 'Izin/Sakit/Cuti (hari)', 'Alpa (hari)', 'JP Aktual', 'JP Terjadwal', 'JP %'];
        $rows[] = [
            $summary['employees'],
            $summary['employee_days'],
            $summary['hadir'],
            $summary['telat'],
            $summary['izin_sakit_cuti'],
            $summary['alpa'],
            $summary['actual_jp'],
            $summary['scheduled_jp'],
            $summary['jp_percent'] ?? '-',
        ];
        $rows[] = [];
        $rows[] = ['TEMUAN'];
        $rows[] = ['Alpa (hari)', $summary['alpa']];
        $rows[] = ['Hari hadir tanpa JP', $summary['hadir_tanpa_jp']];
        $rows[] = ['Hari JP kurang', $summary['jp_kurang']];
        $rows[] = ['Hari izin tetapi mengajar', $summary['izin_mengajar']];
        $rows[] = ['Kelas belum terpantau', $summary['kelas_belum']];
        $rows[] = [];
        $rows[] = ['MATRIKS PEGAWAI'];
        $rows[] = ['Pegawai', 'Unit', 'Hadir (hari)', 'Telat (hari)', 'Izin (hari)', 'Sakit (hari)', 'Cuti (hari)', 'Alpa (hari)', 'JP Aktual', 'JP Terjadwal', 'Temuan'];

        foreach ($this->data['matrix'] as $employee) {
            $rows[] = [
                $employee['pegawai'],
                $employee['unit'],
                $employee['hadir'],
                $employee['telat'],
                $employee['izin'],
                $employee['sakit'],
                $employee['cuti'],
                $employee['alpa'],
                $employee['is_guru'] ? $employee['actual_jp'] : '-',
                $employee['is_guru'] ? $employee['scheduled_jp'] : '-',
                $employee['temuan'],
            ];
        }

        $rows[] = [];
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
        $rows[] = ['DETAIL TEMUAN'];
        $rows[] = ['Jenis', 'Pegawai', 'Unit', 'Tanggal', 'Keterangan'];

        foreach ($this->data['details']['alpa'] as $detail) {
            $rows[] = [
                'Alpa',
                $detail['pegawai'],
                $detail['unit'],
                $detail['tanggal'],
                $detail['sumber'],
            ];
        }

        foreach ($this->data['details']['hadir_tanpa_jp'] as $detail) {
            $rows[] = [
                'Hari hadir tanpa JP',
                $detail['pegawai'],
                $detail['unit'],
                $detail['tanggal'],
                'JP terjadwal '.$this->fmtJp($detail['scheduled_jp']).', JP aktual '.$this->fmtJp($detail['actual_jp']),
            ];
        }

        foreach ($this->data['details']['jp_kurang'] as $detail) {
            $rows[] = [
                'Hari JP kurang',
                $detail['pegawai'],
                $detail['unit'],
                $detail['tanggal'],
                'JP terjadwal '.$this->fmtJp($detail['scheduled_jp']).', JP aktual '.$this->fmtJp($detail['actual_jp']).', kurang '.$this->fmtJp($detail['kurang']),
            ];
        }

        foreach ($this->data['details']['izin_mengajar'] as $detail) {
            $rows[] = [
                'Hari izin tetapi mengajar',
                $detail['pegawai'],
                $detail['unit'],
                $detail['tanggal'],
                'Izin '.$detail['izin'].', realisasi '.$detail['realisasi'],
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
            'K' => 32,
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
