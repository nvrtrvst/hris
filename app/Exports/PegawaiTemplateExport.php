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

                // Daftar jabatan dari master data -> sheet tersembunyi sebagai sumber dropdown.
                // Dibaca dari DB setiap kali template di-download, jadi jabatan baru otomatis muncul.
                $jabatanNames = Jabatan::orderBy('nama')->pluck('nama')->values();

                if ($jabatanNames->isEmpty()) {
                    return;
                }

                // Semua daftar dropdown diletakkan di SATU sheet tersembunyi (bukan list
                // inline) karena list inline data validation tidak muncul di sebagian
                // versi Excel/WPS. Referensi antar-sheet jauh lebih kompatibel.
                $listSheet = new Worksheet($sheet->getParent(), 'DaftarPegawai');
                $listSheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
                $unitNames = UnitSekolah::orderBy('nama')->pluck('nama')->values();

                $listSheet->setCellValue('A1', 'Nama Jabatan');
                $listSheet->setCellValue('B1', 'Pendidikan Terakhir');
                $listSheet->setCellValue('C1', 'Status Kepegawaian');
                $listSheet->setCellValue('D1', 'Unit Sekolah');
                foreach ($jabatanNames as $i => $name) {
                    $listSheet->setCellValue('A'.($i + 2), $name);
                }
                foreach (PegawaiConstants::PENDIDIKAN_TERAKHIR as $i => $p) {
                    $listSheet->setCellValue('B'.($i + 2), $p);
                }
                foreach (PegawaiConstants::STATUS_KEPEGAWAIAN as $i => $s) {
                    $listSheet->setCellValue('C'.($i + 2), $s);
                }
                foreach ($unitNames as $i => $u) {
                    $listSheet->setCellValue('D'.($i + 2), $u);
                }
                $spreadsheet = $sheet->getParent();
                $spreadsheet->addSheet($listSheet);

                $lastA = $jabatanNames->count() + 1;
                $lastB = count(PegawaiConstants::PENDIDIKAN_TERAKHIR) + 1;
                $lastC = count(PegawaiConstants::STATUS_KEPEGAWAIAN) + 1;
                $lastD = $unitNames->count() + 1;

                // Defined name (named range) — cara paling kompatibel antar aplikasi
                // spreadsheet (Excel, LibreOffice, WPS). Referensi langsung ke sheet
                // tersembunyi kadang diabaikan sebagian aplikasi.
                $spreadsheet->addNamedRange(new NamedRange('DAFTAR_JABATAN', $listSheet, "\$A\$2:\$A\${$lastA}"));
                $spreadsheet->addNamedRange(new NamedRange('DAFTAR_PENDIDIKAN', $listSheet, "\$B\$2:\$B\${$lastB}"));
                $spreadsheet->addNamedRange(new NamedRange('DAFTAR_STATUS', $listSheet, "\$C\$2:\$C\${$lastC}"));
                $spreadsheet->addNamedRange(new NamedRange('DAFTAR_UNIT', $listSheet, "\$D\$2:\$D\${$lastD}"));

                $listValidation = function (string $formula, string $errorTitle, string $error) {
                    $validation = new DataValidation;
                    $validation->setType(DataValidation::TYPE_LIST);
                    $validation->setFormula1($formula);
                    $validation->setAllowBlank(false);
                    // PENTING: properti showDropDown terbalik dengan OOXML. Writer menulis
                    // 'showDropDown="1"' saat properti false — dan '1' artinya panah
                    // dropdown DISEMBUNYIKAN di Excel. Jadi true = dropdown tampil.
                    $validation->setShowDropDown(true);
                    $validation->setShowErrorMessage(true);
                    $validation->setErrorTitle($errorTitle);
                    $validation->setError($error);

                    return $validation;
                };

                // Nama Jabatan (N), Pendidikan Terakhir (M), Status Kepegawaian (K), Unit (O).
                $sheet->setDataValidation('N2:N500', $listValidation('DAFTAR_JABATAN', 'Jabatan tidak valid', 'Pilih jabatan dari daftar yang tersedia.'));
                $sheet->setDataValidation('M2:M500', $listValidation('DAFTAR_PENDIDIKAN', 'Pendidikan tidak valid', 'Pilih jenjang pendidikan dari daftar yang tersedia.'));
                $sheet->setDataValidation('K2:K500', $listValidation('DAFTAR_STATUS', 'Status tidak valid', 'Pilih status kepegawaian dari daftar yang tersedia.'));
                $sheet->setDataValidation('O2:O500', $listValidation('DAFTAR_UNIT', 'Unit tidak valid', 'Pilih unit dari daftar yang tersedia.'));
            },
        ];
    }
}
