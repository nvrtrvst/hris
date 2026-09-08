<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Template import jadwal — 2 baris contoh + dropdown hari & jenis jadwal
 * (data validation) supaya admin tidak salah ketik.
 */
class JadwalTemplateExport implements FromArray, ShouldAutoSize, WithStyles
{
    public function array(): array
    {
        return [
            ['Hari', 'Kelas', 'Nama Guru', 'Mata Pelajaran', 'Jam Mulai', 'Jam Selesai', 'Jenis Jadwal', 'Tahun Ajaran', 'Semester'],
            ['Senin', '7 - A', 'Contoh: Ahmad Fauzi', 'Matematika', '08:00', '08:45', 'mengajar', '2026/2027', 1],
            ['Senin', '7 - A', 'Contoh: Ahmad Fauzi', 'Matematika', '08:45', '09:30', 'mengajar', '2026/2027', 1],
            ['Selasa', '', 'Contoh: Siti Maryam', '', '07:00', '15:00', 'piket', '2026/2027', 1],
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

        // Dropdown kolom G (Jenis Jadwal).
        $jenisValidation = $sheet->getCell('G2')->getDataValidation();
        $jenisValidation->setType(DataValidation::TYPE_LIST);
        $jenisValidation->setFormula1('"mengajar,piket,ekskul,shift_satpam,shift_kebersihan,lainnya"');
        $jenisValidation->setShowDropDown(true);
        $sheet->setDataValidation('G2:G500', $jenisValidation);

        return [
            1 => ['font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FF0F3D3E']]],
        ];
    }
}
