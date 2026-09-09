<?php

namespace App\Exports;

use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\UnitSekolah;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Template import jadwal — 2 sheet:
 *
 * 1. "Jadwal": kolom data (A-J) + dropdown Hari/Jenis/Guru/Mapel + formula
 *    Jam Selesai otomatis (mulai + Jumlah JP × durasi di Referensi!D2).
 * 2. "Referensi": daftar mapel (semua, dedup nama), daftar guru aktif
 *    (sesuai unit download), durasi JP unit.
 */
class JadwalTemplateExport implements ShouldAutoSize, WithMultipleSheets
{
    /** @var array<int, string> nama guru aktif (unit terpilih / semua) */
    protected array $guruNames;

    /** @var array<int, string> nama mapel semua (dedup) */
    protected array $mapelNames;

    protected int $durasiJp;

    public function __construct(?array $guruNames = null, ?array $mapelNames = null, ?int $durasiJp = null)
    {
        $this->guruNames = $guruNames ?? [];
        $this->mapelNames = $mapelNames ?? [];
        $this->durasiJp = $durasiJp ?? 40;
    }

    public function sheets(): array
    {
        return [
            new JadwalTemplateJadwalSheet($this->guruNames, $this->mapelNames, $this->durasiJp),
            new JadwalTemplateReferensiSheet($this->mapelNames, $this->guruNames, $this->durasiJp),
        ];
    }

    /**
     * Factory: kumpulkan data referensi dari DB.
     *
     * @param  int|null  $unitId  unit yang dipilih admin saat download —
     *                            menentukan daftar guru & durasi JP.
     */
    public static function build(?int $unitId = null): self
    {
        // Mapel: semua campur (global + semua unit), dedup by nama tampil.
        $mapelNames = MataPelajaran::orderBy('nama')
            ->get()
            ->map(fn ($m) => trim($m->nama))
            ->unique(fn ($n) => mb_strtolower($n))
            ->values()
            ->all();

        // Guru: aktif, sesuai unit (atau semua bila tanpa unit).
        $guruQuery = Pegawai::where('status_aktif', 'aktif')->orderBy('nama_lengkap');
        if ($unitId) {
            $guruQuery->whereHas('units', fn ($q) => $q->where('unit_sekolah.id', $unitId));
        }
        $guruNames = $guruQuery->pluck('nama_lengkap')->all();

        $durasiJp = $unitId
            ? (int) (UnitSekolah::find($unitId)?->durasi_jp ?: 40)
            : 40;

        return new self($guruNames, $mapelNames, $durasiJp);
    }
}

class JadwalTemplateJadwalSheet implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    public function __construct(
        protected array $guruNames,
        protected array $mapelNames,
        protected int $durasiJp,
    ) {}

    public function title(): string
    {
        return 'Jadwal';
    }

    public function array(): array
    {
        return [
            ['Hari', 'Kelas', 'Nama Guru', 'Mata Pelajaran', 'Jam Mulai', 'Jumlah JP', 'Jam Selesai (otomatis)', 'Jenis Jadwal', 'Tahun Ajaran', 'Semester'],
            // Contoh: formula G menghitung 07:00 + 2 JP × durasi (Referensi!D2).
            ['Senin', '7 - A', $this->guruNames[0] ?? 'Ganti: Nama Guru', $this->mapelNames[0] ?? 'Matematika', '07:00', 2, '', 'mengajar', '2026/2027', 1],
            ['Senin', '7 - A', $this->guruNames[0] ?? 'Ganti: Nama Guru', $this->mapelNames[0] ?? 'Matematika', '08:20', 2, '', 'mengajar', '2026/2027', 1],
            // Non-mengajar: kosongkan Jumlah JP, isi Jam Selesai manual.
            ['Selasa', '', $this->guruNames[1] ?? 'Ganti: Nama Staf', '', '07:00', '', '15:00', 'piket', '2026/2027', 1],
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Dropdown statis: Hari (A) & Jenis (H).
        $hariValidation = $sheet->getCell('A2')->getDataValidation();
        $hariValidation->setType(DataValidation::TYPE_LIST);
        $hariValidation->setFormula1('"Senin,Selasa,Rabu,Kamis,Jumat,Sabtu,Minggu"');
        $hariValidation->setShowDropDown(true);
        $sheet->setDataValidation('A2:A500', $hariValidation);

        $jenisValidation = $sheet->getCell('H2')->getDataValidation();
        $jenisValidation->setType(DataValidation::TYPE_LIST);
        $jenisValidation->setFormula1('"mengajar,piket,ekskul,shift_satpam,shift_kebersihan,lainnya"');
        $jenisValidation->setShowDropDown(true);
        $sheet->setDataValidation('H2:H500', $jenisValidation);

        // Dropdown dinamis dari sheet Referensi: Guru (C) & Mapel (D).
        // Formula list pakai OFFSET-COUNTA supaya panjang daftar fleksibel.
        $guruValidation = $sheet->getCell('C2')->getDataValidation();
        $guruValidation->setType(DataValidation::TYPE_LIST);
        $guruValidation->setFormula1('=OFFSET(Referensi!$B$3,0,0,COUNTA(Referensi!$B$3:$B$1000),1)');
        $guruValidation->setShowDropDown(true);
        $guruValidation->setErrorTitle('Guru tidak ditemukan');
        $guruValidation->setError('Pilih guru dari daftar (sesuai unit yang dipilih saat mengunduh template).');
        $guruValidation->setShowErrorMessage(true);
        $sheet->setDataValidation('C2:C500', $guruValidation);

        $mapelValidation = $sheet->getCell('D2')->getDataValidation();
        $mapelValidation->setType(DataValidation::TYPE_LIST);
        $mapelValidation->setFormula1('=OFFSET(Referensi!$A$3,0,0,COUNTA(Referensi!$A$3:$A$1000),1)');
        $mapelValidation->setShowDropDown(true);
        $mapelValidation->setErrorTitle('Mapel tidak ada di daftar');
        $mapelValidation->setError('Pilih mapel dari daftar. Mapel baru otomatis dibuat saat import bila ditulis manual.');
        $mapelValidation->setShowErrorMessage(false); // warning saja — boleh ketik baru.
        $sheet->setDataValidation('D2:D500', $mapelValidation);

        // Formula jam selesai baris 2-500 — durasi JP dari Referensi!D2.
        // Cell yang sudah diisi nilai manual (contoh piket) tidak ditimpa.
        for ($r = 2; $r <= 500; $r++) {
            $existing = $sheet->getCell("G{$r}")->getValue();
            if ($existing !== null && $existing !== '' && ! str_starts_with((string) $existing, '=')) {
                continue;
            }
            $sheet->setCellValue(
                "G{$r}",
                "=IF(OR(\$E{$r}=\"\",\$F{$r}=\"\"),\"\",IF(ISNUMBER(\$E{$r}),\$E{$r},TIMEVALUE(\$E{$r}))+Referensi!\$D\$2*\$F{$r}/1440)"
            );
        }

        // Format: F angka (cegah Excel baca tanggal), E/G jam.
        $sheet->getStyle('F2:F500')->getNumberFormat()->setFormatCode('0');
        $sheet->getStyle('E2:E500')->getNumberFormat()->setFormatCode('hh:mm');
        $sheet->getStyle('G2:G500')->getNumberFormat()->setFormatCode('hh:mm');

        return [
            1 => ['font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FF0F3D3E']]],
        ];
    }
}

class JadwalTemplateReferensiSheet implements FromArray, WithTitle
{
    public function __construct(
        protected array $mapelNames,
        protected array $guruNames,
        protected int $durasiJp,
    ) {}

    public function title(): string
    {
        return 'Referensi';
    }

    public function array(): array
    {
        $rows = [
            // Baris 1: judul kolom.
            ['Daftar Mata Pelajaran', 'Daftar Guru (aktif, sesuai unit unduhan)', 'Konfigurasi', ''],
            // Baris 2: D2 = durasi JP numerik — dirujuk formula Jam Selesai
            // (sheet Jadwal) dan tidak boleh bercampur teks.
            ['', '', 'Durasi JP (menit) →', $this->durasiJp],
            // Baris 3+ = daftar — dipakai formula dropdown OFFSET di sheet Jadwal.
        ];

        $max = max(count($this->mapelNames), count($this->guruNames));
        for ($i = 0; $i < $max; $i++) {
            $rows[] = [
                $this->mapelNames[$i] ?? '',
                $this->guruNames[$i] ?? '',
                '',
                '',
            ];
        }

        return $rows;
    }
}
