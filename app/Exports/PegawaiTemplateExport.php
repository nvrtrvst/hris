<?php

namespace App\Exports;

use App\Constants\PegawaiConstants;
use App\Models\Jabatan;
use App\Models\UnitSekolah;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\NamedRange;

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
            'Status Kepegawaian (dropdown)',
            'Tanggal Mulai Kerja (YYYY-MM-DD)',
            'Pendidikan Terakhir (dropdown)',
            'Nama Jabatan (pilih dari dropdown)',
            'Unit Sekolah (dropdown — kosongkan jika satu unit)',
            'Email (wajib — untuk login pegawai)',
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
                'S1',
                'Guru Mata Pelajaran',
                'SMP',
                'budi.santoso@yayasan.com',
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
                'SMK/Sederajat',
                'Kasir',
                'SMA',
                'siti.aminah@yayasan.com',
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $spreadsheet = $sheet->getParent();

                // Load master data for dropdowns
                $jabatanNames = Jabatan::orderBy('nama')->pluck('nama')->values();
                $unitNames = UnitSekolah::orderBy('nama')->pluck('nama')->values();
                $pendidikan = collect(PegawaiConstants::PENDIDIKAN_TERAKHIR);
                $statusList = collect(PegawaiConstants::STATUS_KEPEGAWAIAN);

                if ($jabatanNames->isEmpty()) {
                    return;
                }

                // ================================================================
                // IMPORTANT: Reference data goes on the SAME sheet (columns R-U).
                // NOT on a separate hidden sheet — PhpSpreadsheet/LibreOffice/WPS
                // may switch active sheet on save, causing import to read wrong data.
                // ================================================================
                $refStartCol = 'R'; // Column 18

                // Row 1: headers for reference data
                $sheet->setCellValue('R1', '_Jabatan');
                $sheet->setCellValue('S1', '_Pendidikan');
                $sheet->setCellValue('T1', '_Status');
                $sheet->setCellValue('U1', '_Unit');

                // Fill reference data
                foreach ($jabatanNames as $i => $name) {
                    $sheet->setCellValue('R'.($i + 2), $name);
                }
                foreach ($pendidikan as $i => $p) {
                    $sheet->setCellValue('S'.($i + 2), $p);
                }
                foreach ($statusList as $i => $s) {
                    $sheet->setCellValue('T'.($i + 2), $s);
                }
                foreach ($unitNames as $i => $u) {
                    $sheet->setCellValue('U'.($i + 2), $u);
                }

                $lastJabatan = $jabatanNames->count() + 1;
                $lastPendidikan = $pendidikan->count() + 1;
                $lastStatus = $statusList->count() + 1;
                $lastUnit = $unitNames->count() + 1;

                // Hide reference columns (R-U) — data is there but invisible to user
                $sheet->getColumnDimension('R')->setVisible(false);
                $sheet->getColumnDimension('S')->setVisible(false);
                $sheet->getColumnDimension('T')->setVisible(false);
                $sheet->getColumnDimension('U')->setVisible(false);

                // Named ranges on the SAME sheet — fully compatible across Excel/LibreOffice/WPS
                $sheetName = $sheet->getTitle();
                $spreadsheet->addNamedRange(new NamedRange('DAFTAR_JABATAN', $sheet, "'{$sheetName}'!\$R\$2:\$R\${$lastJabatan}"));
                $spreadsheet->addNamedRange(new NamedRange('DAFTAR_PENDIDIKAN', $sheet, "'{$sheetName}'!\$S\$2:\$S\${$lastPendidikan}"));
                $spreadsheet->addNamedRange(new NamedRange('DAFTAR_STATUS', $sheet, "'{$sheetName}'!\$T\$2:\$T\${$lastStatus}"));
                $spreadsheet->addNamedRange(new NamedRange('DAFTAR_UNIT', $sheet, "'{$sheetName}'!\$U\$2:\$U\${$lastUnit}"));

                $listValidation = function (string $formula, string $errorTitle, string $error) {
                    $validation = new DataValidation;
                    $validation->setType(DataValidation::TYPE_LIST);
                    $validation->setFormula1($formula);
                    $validation->setAllowBlank(false);
                    $validation->setShowDropDown(true);
                    $validation->setShowErrorMessage(true);
                    $validation->setErrorTitle($errorTitle);
                    $validation->setError($error);

                    return $validation;
                };

                // Apply dropdown validation to data columns
                $sheet->setDataValidation('N2:N500', $listValidation('DAFTAR_JABATAN', 'Jabatan tidak valid', 'Pilih jabatan dari daftar yang tersedia.'));
                $sheet->setDataValidation('M2:M500', $listValidation('DAFTAR_PENDIDIKAN', 'Pendidikan tidak valid', 'Pilih jenjang pendidikan dari daftar yang tersedia.'));
                $sheet->setDataValidation('K2:K500', $listValidation('DAFTAR_STATUS', 'Status tidak valid', 'Pilih status kepegawaian dari daftar yang tersedia.'));
                $sheet->setDataValidation('O2:O500', $listValidation('DAFTAR_UNIT', 'Unit tidak valid', 'Pilih unit dari daftar yang tersedia.'));
            },
        ];
    }
}
