<?php

namespace App\Exports;

use App\Models\Presensi;
use App\Models\UnitSekolah;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class LaporanPresensiExport implements FromCollection, ShouldAutoSize, WithCustomStartCell, WithEvents, WithHeadings, WithMapping
{
    protected $start_date;

    protected $end_date;

    protected $unit_id;

    protected $jenis;

    protected $tipe;

    public function __construct($start_date, $end_date, $unit_id = null, $jenis = null, $tipe = null)
    {
        $this->start_date = $start_date;
        $this->end_date = $end_date;
        $this->unit_id = $unit_id;
        $this->jenis = $jenis;
        $this->tipe = $tipe;
    }

    public function collection()
    {
        // Eager-load jabatans + jadwal.mapel untuk kolom Jenis & Tipe (hindari N+1 di map()).
        $query = Presensi::with(['pegawai.jabatans', 'unitSekolah', 'jadwal.pegawaiMapel.mataPelajaran'])
            ->whereBetween('tanggal', [$this->start_date, $this->end_date]);

        if ($this->unit_id) {
            $query->where('unit_sekolah_id', $this->unit_id);
        }

        $this->applyJenisFilter($query);
        $this->applyTipeFilter($query);

        return $query->orderBy('tanggal', 'asc')->get();
    }

    /**
     * Filter tipe presensi: kantor = kehadiran harian (tanpa jadwal),
     * mengajar = presensi per JP. Null = semua.
     */
    protected function applyTipeFilter($query): void
    {
        if ($this->tipe === 'kantor') {
            $query->whereNull('jadwal_id')
                ->where(fn ($q) => $q->where('tipe_presensi', 'kantor')->orWhereNull('tipe_presensi'));
        } elseif ($this->tipe === 'mengajar') {
            $query->whereNotNull('jadwal_id')->where('tipe_presensi', 'mengajar');
        }
    }

    /**
     * Filter jenis pegawai (Dapodik-style): pendidik = punya jabatan guru,
     * kependidikan = tidak punya jabatan guru sama sekali.
     */
    protected function applyJenisFilter($query): void
    {
        if ($this->jenis === 'pendidik') {
            $query->whereHas('pegawai', fn ($q) => $q->guru());
        } elseif ($this->jenis === 'kependidikan') {
            $query->whereHas('pegawai', fn ($q) => $q->nonGuru());
        }
    }

    /**
     * Tipe presensi untuk laporan: fallback infer untuk row lama yang
     * tipe_presensi-nya NULL (sebelum migrasi kolom ada).
     */
    protected function tipePresensiLabel($presensi): string
    {
        $tipe = $presensi->tipe_presensi;

        if (! $tipe) {
            if ($presensi->is_lembur) {
                return 'Lembur';
            }
            if ($presensi->is_tugas_luar) {
                return 'Tugas Luar';
            }

            return $presensi->jadwal_id ? 'Mengajar' : 'Kantor';
        }

        return ucfirst(str_replace('_', ' ', $tipe));
    }

    public function map($presensi): array
    {
        $pegawai = $presensi->pegawai;

        $tipeLabel = $this->tipePresensiLabel($presensi);

        $row = [
            $presensi->tanggal->format('d/m/Y'),
            $pegawai?->nama_lengkap ?? '-',
            $pegawai?->nip ?? '-',
            $pegawai ? $pegawai->jenisPegawaiLabel() : '-',
            $tipeLabel,
        ];

        // Kolom Mapel/Kelas hanya relevan untuk presensi mengajar —
        // laporan tipe kantor (harian) menyembunyikannya.
        if ($this->tipe !== 'kantor') {
            $mapel = '-';
            $kelas = '-';
            if ($presensi->jadwal) {
                $mapel = $presensi->jadwal->mata_pelajaran?->nama ?? '-';
                $kelas = $presensi->jadwal->kelas_label ?? '-';
            }
            $row[] = $mapel;
            $row[] = $kelas;
        }

        $row[] = $presensi->unitSekolah?->nama ?? '-';
        $row[] = $presensi->jam_masuk ?? '-';
        $row[] = $presensi->jam_keluar ?? '-';
        $row[] = ucfirst($presensi->status);
        $row[] = $presensi->keterangan ?? '-';

        return $row;
    }

    public function headings(): array
    {
        $heads = [
            'Tanggal',
            'Nama Pegawai',
            'NIP',
            'Jenis',
            'Tipe Presensi',
        ];

        if ($this->tipe !== 'kantor') {
            $heads[] = 'Mata Pelajaran';
            $heads[] = 'Kelas';
        }

        $heads[] = 'Unit Sekolah';
        $heads[] = 'Jam Masuk';
        $heads[] = 'Jam Keluar';
        $heads[] = 'Status';
        $heads[] = 'Keterangan';

        return $heads;
    }

    public function startCell(): string
    {
        return 'A6';
    }

    public function registerEvents(): array
    {
        // Kolom terakhir menyesuaikan jumlah headings (kantor = 10, lainnya = 12).
        $lastCol = chr(ord('A') + count($this->headings()) - 1);

        return [
            AfterSheet::class => function (AfterSheet $event) use ($lastCol) {
                $sheet = $event->sheet->getDelegate();

                $namaUnit = 'Semua Unit Sekolah';
                if ($this->unit_id) {
                    $unit = UnitSekolah::find($this->unit_id);
                    $namaUnit = $unit ? $unit->nama : 'Semua Unit Sekolah';
                }

                $periodeStr = Carbon::parse($this->start_date)->format('d/m/Y').' s/d '.Carbon::parse($this->end_date)->format('d/m/Y');

                // Kop Yayasan
                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->setCellValue('A1', 'YAYASAN PENDIDIKAN'); // Ganti dengan nama yayasan asli
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->mergeCells("A2:{$lastCol}2");
                $sheet->setCellValue('A2', 'LAPORAN REKAPITULASI PRESENSI PEGAWAI');
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->mergeCells("A3:{$lastCol}3");
                $sheet->setCellValue('A3', 'Periode: '.$periodeStr.' | Unit: '.$namaUnit);
                $sheet->getStyle('A3')->getFont()->setItalic(true);
                $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Styling for Headings
                $sheet->getStyle("A6:{$lastCol}6")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FF0F3D3E'], // Primary color Yayasan
                    ],
                ]);
            },
        ];
    }
}
