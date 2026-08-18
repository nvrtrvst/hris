<?php

namespace App\Exports;

use App\Models\Jabatan;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PegawaiTemplateExport implements FromArray, WithEvents, WithHeadings
{
    public function headings(): array
    {
        return [
            'NIK',
            'NIP',
            'Nama Lengkap',
            'Tempat Lahir',
            'Tanggal Lahir (YYYY-MM-DD)',
            'Jenis Kelamin (L/P)',
            'Agama',
            'Status Pernikahan',
            'No HP',
            'Alamat KTP',
            'Status Kepegawaian (tetap/kontrak/honorer/gtt)',
            'Tanggal Mulai Kerja (YYYY-MM-DD)',
            'Pendidikan Terakhir',
            'Nama Jabatan (pilih dari dropdown)',
        ];
    }

    public function array(): array
    {
        return [
            [
                '1234567890123456',
                '198001012020011001',
                'Budi Santoso',
                'Jakarta',
                '1980-01-01',
                'L',
                'Islam',
                'Menikah',
                '081234567890',
                'Jl. Contoh Alamat No. 123',
                'tetap',
                '2020-01-01',
                'S1 Pendidikan',
                'Guru Mata Pelajaran',
            ],
            [
                '1234567890123457',
                '199205012023011002',
                'Siti Aminah',
                'Bandung',
                '1992-05-01',
                'P',
                'Islam',
                'Menikah',
                '081298765432',
                'Jl. Contoh Alamat No. 456',
                'kontrak',
                '2023-01-01',
                'SMK Akuntansi',
                'Kasir',
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Daftar jabatan dari master data -> sheet tersembunyi sebagai sumber dropdown.
                // Dibaca dari DB setiap kali template di-download, jadi jabatan baru otomatis muncul.
                $names = Jabatan::orderBy('nama')->pluck('nama')->values();

                if ($names->isEmpty()) {
                    return;
                }

                $listSheet = new Worksheet($sheet->getParent(), 'DaftarJabatan');
                $listSheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
                foreach ($names as $i => $name) {
                    $listSheet->setCellValue('A'.($i + 1), $name);
                }
                $sheet->getParent()->addSheet($listSheet);

                // Dropdown di kolom N (Nama Jabatan) untuk baris data 2..500.
                $validation = new DataValidation;
                $validation->setType(DataValidation::TYPE_LIST);
                $validation->setFormula1('DaftarJabatan!$A$1:$A'.$names->count());
                $validation->setAllowBlank(false);
                $validation->setShowErrorMessage(true);
                $validation->setErrorTitle('Jabatan tidak valid');
                $validation->setError('Pilih jabatan dari daftar yang tersedia.');

                $sheet->setDataValidation('N2:N500', $validation);
            },
        ];
    }
}
