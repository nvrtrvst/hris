<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Template import jadwal — jam selesai otomatis dihitung dari Jam Mulai +
 * Jumlah JP × Durasi JP (cell L1). Dropdown hari & jenis jadwal.
 *
 * Kolom: Hari | Kelas | Nama Guru | Mata Pelajaran | Jam Mulai | Jumlah JP |
 *        Jam Selesai (otomatis) | Jenis Jadwal | Tahun Ajaran | Semester
 * Cell K1: label durasi, L1: durasi JP dalam menit (default 40).
 */
class JadwalTemplateExport implements FromArray, ShouldAutoSize, WithStyles
{
    public function array(): array
    {
        // Formula kolom G (jam selesai otomatis) dipasang untuk SEMUA baris
        // (2-500) di styles() — baris contoh di sini tanpa kolom G.
        return [
            ['Hari', 'Kelas', 'Nama Guru', 'Mata Pelajaran', 'Jam Mulai', 'Jumlah JP', 'Jam Selesai (otomatis)', 'Jenis Jadwal', 'Tahun Ajaran', 'Semester', 'Durasi JP (menit):', 40],
            // Contoh: 07:00 + 2 JP × 40 menit = 07:00-08:20 (JP berikut 08:20 dst).
            ['Senin', '7 - A', 'Ganti: Nama Guru', 'Matematika', '07:00', 2, '', 'mengajar', '2026/2027', 1, '', ''],
            ['Senin', '7 - A', 'Ganti: Nama Guru', 'Matematika', '08:20', 2, '', 'mengajar', '2026/2027', 1, '', ''],
            // Jenis non-mengajar: kosongkan Jumlah JP, isi Jam Selesai manual
            // (hapus formula baris ini terlebih dulu atau timpa dengan nilai).
            ['Selasa', '', 'Ganti: Nama Staf', '', '07:00', '', '15:00', 'piket', '2026/2027', 1, '', ''],
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Dropdown kolom A (Hari): baris 2-500.
        $hariValidation = $sheet->getCell('A2')->getDataValidation();
        $hariValidation->setType(DataValidation::TYPE_LIST);
        $hariValidation->setFormula1('"Senin,Selasa,Rabu,Kamis,Jumat,Sabtu,Minggu"');
        $hariValidation->setShowDropDown(true);
        $sheet->setDataValidation('A2:A500', $hariValidation);

        // Dropdown kolom H (Jenis Jadwal).
        $jenisValidation = $sheet->getCell('H2')->getDataValidation();
        $jenisValidation->setType(DataValidation::TYPE_LIST);
        $jenisValidation->setFormula1('"mengajar,piket,ekskul,shift_satpam,shift_kebersihan,lainnya"');
        $jenisValidation->setShowDropDown(true);
        $sheet->setDataValidation('H2:H500', $jenisValidation);

        // Formula jam selesai dipasang sampai baris 500 — semua baris yang
        // user isi otomatis terhitung, bukan cuma baris contoh. Cell yang
        // sudah diisi nilai manual (contoh piket) tidak ditimpa.
        for ($r = 2; $r <= 500; $r++) {
            $existing = $sheet->getCell("G{$r}")->getValue();
            if ($existing !== null && $existing !== '' && ! str_starts_with((string) $existing, '=')) {
                continue;
            }
            $sheet->setCellValue(
                "G{$r}",
                "=IF(OR(\$E{$r}=\"\",\$F{$r}=\"\"),\"\",IF(ISNUMBER(\$E{$r}),\$E{$r},TIMEVALUE(\$E{$r}))+\$L\$1*\$F{$r}/1440)"
            );
        }

        // Kolom waktu tampil rapi sebagai hh:mm.
        $sheet->getStyle('E2:G500')->getNumberFormat()->setFormatCode('hh:mm');

        return [
            1 => ['font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FF0F3D3E']]],
        ];
    }
}
