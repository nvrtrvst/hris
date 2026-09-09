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

/**
 * Template Excel import pegawai — format GTYS Yayasan.
 *
 * 17 kolom (A–Q). Kolom wajib: A–Q. NIK, Agama, Status Pernikahan tidak
 * ada di template (diisi manual via Edit Page bila perlu).
 *
 * Kolom dengan dropdown validasi: E (Pendidikan), K (Jabatan),
 * P (Status Kepegawaian), Q (Unit Sekolah).
 *
 * Referensi dropdown ditulis di kolom V–Y (sembunyi) pada sheet yang
 * SAMA — bukan sheet terpisah — supaya import tidak salah baca ketika
 * user save di Excel/LibreOffice/WPS (lihat commit terkait).
 */
class PegawaiTemplateExport implements FromArray, WithEvents, WithHeadings
{
    public function headings(): array
    {
        return [
            'NAMA',
            'TEMPAT LAHIR',
            'TANGGAL LAHIR (YYYY-MM-DD)',
            'JENIS KELAMIN (L/P)',
            'JENJANG / JURUSAN',
            'TAHUN LULUS',
            'ASAL SEKOLAH / PERGURUAN TINGGI',
            'NOMOR SK',
            'TANGGAL SK (YYYY-MM-DD)',
            'TMT MENGAJAR (YYYY-MM-DD)',
            'JABATAN',
            'NUPTK',
            'ALAMAT',
            'EMAIL (untuk login pegawai)',
            'KONTAK',
            'STATUS (dropdown)',
            'UNIT SEKOLAH',
        ];
    }

    public function array(): array
    {
        return [
            [
                'Rizal Trismawan',
                'Garut',
                '1988-02-28',
                'L',
                'S2 / B.INDONESIA',
                '2015',
                'UPI Bandung',
                '001/SK/YYS/2015',
                '2015-03-01',
                '2015-03-01',
                'Guru Mata Pelajaran',
                '1234567890123456',
                'Jl. Contoh Alamat No. 123, Garut',
                'rizal.trismawan@yayasan.com',
                '081234567890',
                'guru_tetap_yayasan',
                'SMP',
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $spreadsheet = $sheet->getParent();

                // Load master data for dropdowns.
                $jabatanNames = Jabatan::orderBy('nama')->pluck('nama')->values();
                $unitNames = UnitSekolah::orderBy('nama')->pluck('nama')->values();
                $pendidikan = collect(PegawaiConstants::PENDIDIKAN_TERAKHIR);
                $statusList = collect(PegawaiConstants::STATUS_KEPEGAWAIAN);

                if ($jabatanNames->isEmpty()) {
                    return;
                }

                // Reference data on the SAME sheet (columns V–Y, hidden).
                // Row 1: headers for reference data.
                $sheet->setCellValue('V1', '_Jabatan');
                $sheet->setCellValue('W1', '_Pendidikan');
                $sheet->setCellValue('X1', '_Status');
                $sheet->setCellValue('Y1', '_Unit');

                foreach ($jabatanNames as $i => $name) {
                    $sheet->setCellValue('V'.($i + 2), $name);
                }
                foreach ($pendidikan as $i => $p) {
                    $sheet->setCellValue('W'.($i + 2), $p);
                }
                foreach ($statusList as $i => $s) {
                    $sheet->setCellValue('X'.($i + 2), $s);
                }
                foreach ($unitNames as $i => $u) {
                    $sheet->setCellValue('Y'.($i + 2), $u);
                }

                $lastJabatan = $jabatanNames->count() + 1;
                $lastPendidikan = $pendidikan->count() + 1;
                $lastStatus = $statusList->count() + 1;
                $lastUnit = $unitNames->count() + 1;

                // Hide reference columns.
                $sheet->getColumnDimension('V')->setVisible(false);
                $sheet->getColumnDimension('W')->setVisible(false);
                $sheet->getColumnDimension('X')->setVisible(false);
                $sheet->getColumnDimension('Y')->setVisible(false);

                // Named ranges on the SAME sheet.
                $sheetName = $sheet->getTitle();
                $spreadsheet->addNamedRange(new NamedRange('DAFTAR_JABATAN', $sheet, "'{$sheetName}'!\$V\$2:\$V\${$lastJabatan}"));
                $spreadsheet->addNamedRange(new NamedRange('DAFTAR_PENDIDIKAN', $sheet, "'{$sheetName}'!\$W\$2:\$W\${$lastPendidikan}"));
                $spreadsheet->addNamedRange(new NamedRange('DAFTAR_STATUS', $sheet, "'{$sheetName}'!\$X\$2:\$X\${$lastStatus}"));
                $spreadsheet->addNamedRange(new NamedRange('DAFTAR_UNIT', $sheet, "'{$sheetName}'!\$Y\$2:\$Y\${$lastUnit}"));

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

                // Apply dropdown validation to data columns (A..Q → 1..17).
                // E = Pendidikan, K = Jabatan, P = Status, Q = Unit.
                $sheet->setDataValidation('K2:K500', $listValidation('DAFTAR_JABATAN', 'Jabatan tidak valid', 'Pilih jabatan dari daftar yang tersedia.'));
                $sheet->setDataValidation('E2:E500', $listValidation('DAFTAR_PENDIDIKAN', 'Jenjang tidak valid', 'Pilih jenjang dari daftar. Jurusan (jika ada) ketik manual dipisah dengan " / ". Contoh: "S2 / B.INDONESIA".'));
                $sheet->setDataValidation('P2:P500', $listValidation('DAFTAR_STATUS', 'Status tidak valid', 'Pilih status kepegawaian dari daftar yang tersedia.'));
                $sheet->setDataValidation('Q2:Q500', $listValidation('DAFTAR_UNIT', 'Unit tidak valid', 'Pilih unit dari daftar yang tersedia.'));
            },
        ];
    }
}
