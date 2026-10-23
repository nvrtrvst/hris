<?php

namespace App\Exports;

use App\Helpers\FileHelper;
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
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Laporan Lemburan — riwayat lembur per tanggal (opsi audit lengkap):
 * semua status (pending/disetujui/ditolak, bisa difilter), durasi,
 * lokasi + jarak + koordinat masuk/keluar, dan foto bukti (hyperlink).
 */
class LaporanLemburanExport implements FromCollection, ShouldAutoSize, WithCustomStartCell, WithEvents, WithHeadings, WithMapping
{
    use FormulaEscapable;

    public const STATUS_LABELS = ['pending' => 'Menunggu', 'disetujui' => 'Disetujui', 'ditolak' => 'Ditolak'];

    protected $start_date;

    protected $end_date;

    protected $unit_id;

    protected $jenis;

    protected $search;

    protected $lembur_status;

    public function __construct($start_date, $end_date, $unit_id = null, $jenis = null, $search = null, $lembur_status = 'semua')
    {
        $this->start_date = $start_date;
        $this->end_date = $end_date;
        $this->unit_id = $unit_id;
        $this->jenis = $jenis;
        $this->search = $search;
        $this->lembur_status = $lembur_status ?: 'semua';
    }

    public function collection()
    {
        // Eager-load jabatans untuk kolom Jenis + lokasi/koordinat bukti (hindari N+1 di map()).
        $query = Presensi::with([
            'pegawai.units', 'pegawai.jabatans',
            'unitSekolah:id,nama',
            'unitLokasiMasuk:id,nama,unit_sekolah_id',
            'unitLokasiKeluar:id,nama,unit_sekolah_id',
        ])
            ->where('is_lembur', true)
            ->whereNotNull('jam_masuk')
            ->whereBetween('tanggal', [$this->start_date, $this->end_date]);

        if ($this->lembur_status !== 'semua' && in_array($this->lembur_status, ['pending', 'disetujui', 'ditolak'], true)) {
            $query->where('lembur_status', $this->lembur_status);
        }

        if ($this->unit_id) {
            $query->whereHas('pegawai.units', function ($q) {
                $q->where('unit_sekolah.id', $this->unit_id);
            });
        }

        if ($this->jenis === 'pendidik') {
            $query->whereHas('pegawai', fn ($q) => $q->guru());
        } elseif ($this->jenis === 'kependidikan') {
            $query->whereHas('pegawai', fn ($q) => $q->nonGuru());
        }

        if ($this->search !== null && $this->search !== '') {
            $query->whereHas('pegawai', fn ($q) => $q->where('nama_lengkap', 'like', '%'.$this->search.'%'));
        }

        return $query->orderBy('tanggal')
            ->orderByRaw('LOWER(nama_lengkap) asc')
            ->join('pegawai', 'pegawai.id', '=', 'presensi.pegawai_id')
            // Kolom eksplisit (prefixed — join pegawai): tanpa `id` (duplikat 2 tabel).
            ->get([
                'presensi.pegawai_id', 'presensi.tanggal', 'presensi.jam_masuk',
                'presensi.jam_keluar', 'presensi.lembur_status',
                'presensi.latitude_masuk', 'presensi.longitude_masuk', 'presensi.jarak_masuk_meter',
                'presensi.latitude_keluar', 'presensi.longitude_keluar', 'presensi.jarak_keluar_meter',
                'presensi.foto_masuk', 'presensi.foto_keluar',
            ]);
    }

    public function map($presensi): array
    {
        $pegawai = $presensi->pegawai;
        $unitName = $pegawai && $pegawai->units->isNotEmpty()
            ? $pegawai->units->first()->nama
            : '-';

        $jamMulai = Carbon::parse($presensi->jam_masuk);
        $jamSelesai = $presensi->jam_keluar ? Carbon::parse($presensi->jam_keluar) : null;
        $totalHours = $jamSelesai ? round($jamMulai->diffInMinutes($jamSelesai) / 60, 2) : null;

        $row = [
            $pegawai?->nuptk ?? '-',
            $pegawai?->nama_lengkap ?? '-',
            $pegawai ? $pegawai->jenisPegawaiLabel() : '-',
            $unitName,
            $presensi->tanggal->format('d/m/Y'),
            self::STATUS_LABELS[$presensi->lembur_status] ?? $presensi->lembur_status,
            $jamMulai->format('H:i:s'),
            $jamSelesai?->format('H:i:s') ?? '—',
            $totalHours ?? '—',
            $this->lokasiMasuk($presensi),
            $presensi->jarak_masuk_meter ?? '—',
            $this->koordinat($presensi->latitude_masuk, $presensi->longitude_masuk),
            $this->lokasiKeluar($presensi),
            $presensi->jarak_keluar_meter ?? '—',
            $this->koordinat($presensi->latitude_keluar, $presensi->longitude_keluar),
            $presensi->foto_masuk ? FileHelper::fotoUrl($presensi->foto_masuk) : '',
            $presensi->foto_keluar ? FileHelper::fotoUrl($presensi->foto_keluar) : '',
        ];

        return array_map([self::class, 'escapeFormula'], $row);
    }

    private function lokasiMasuk($presensi): string
    {
        $unit = $presensi->unitLokasiMasuk;

        return $unit ? trim(($presensi->unitSekolah?->nama ? $presensi->unitSekolah->nama.' ' : '').$unit->nama) : ($presensi->unitSekolah?->nama ?? '-');
    }

    private function lokasiKeluar($presensi): string
    {
        $unit = $presensi->unitLokasiKeluar;

        if (! $unit) {
            return $presensi->jam_keluar ? ($presensi->unitSekolah?->nama ?? '-') : '—';
        }

        return trim(($presensi->unitSekolah?->nama ? $presensi->unitSekolah->nama.' ' : '').$unit->nama);
    }

    private function koordinat($lat, $lng): string
    {
        return $lat !== null && $lng !== null
            ? number_format((float) $lat, 6).', '.number_format((float) $lng, 6)
            : '—';
    }

    public function headings(): array
    {
        return [
            'NIP',
            'Nama Pegawai',
            'Jenis',
            'Unit Sekolah',
            'Tanggal',
            'Status',
            'Jam Mulai',
            'Jam Selesai',
            'Total Jam',
            'Lokasi Masuk',
            'Jarak Masuk (m)',
            'Koordinat Masuk',
            'Lokasi Keluar',
            'Jarak Keluar (m)',
            'Koordinat Keluar',
            'Foto Masuk',
            'Foto Keluar',
        ];
    }

    public function startCell(): string
    {
        return 'A7';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = 'Q';

                $namaUnit = 'Semua Unit Sekolah';
                $kopUnit = $this->unit_id ? UnitSekolah::find($this->unit_id) : UnitSekolah::where('nama', 'like', 'Yayasan%')->first();
                if ($kopUnit) {
                    $namaUnit = $kopUnit->nama;
                }

                $periodeStr = Carbon::parse($this->start_date)->format('d/m/Y').' s/d '.Carbon::parse($this->end_date)->format('d/m/Y');
                $statusStr = $this->lembur_status === 'semua'
                    ? 'Semua status lembur'
                    : 'Status: '.(self::STATUS_LABELS[$this->lembur_status] ?? $this->lembur_status);

                // Kop Yayasan/Unit
                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->setCellValue('A1', strtoupper($kopUnit?->nama ?? config('yayasan.name')));
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->mergeCells("A2:{$lastCol}2");
                $sheet->setCellValue('A2', 'LAPORAN LEMBUR');
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->mergeCells("A3:{$lastCol}3");
                $sheet->setCellValue('A3', 'Periode: '.$periodeStr.' | Unit: '.$namaUnit.' | '.$statusStr);
                $sheet->getStyle('A3')->getFont()->setItalic(true);
                $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->mergeCells("A4:{$lastCol}4");
                $sheet->setCellValue('A4', 'Foto bukti: klik sel kolom Foto untuk membuka gambar lengkap (burn-in nama/unit/waktu/koordinat). Baris Menunggu/Ditolak belum masuk hitungan payroll.');
                $sheet->getStyle('A4')->getFont()->setItalic(true)->setSize(9)->setColor(new Color('FF6B7280'));

                // Styling for Headings
                $sheet->getStyle("A7:{$lastCol}7")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FFC9A227'],
                    ],
                ]);

                // Foto masuk/keluar (P, Q): hyperlink bila sel terisi URL.
                $lastRow = max(7, $sheet->getHighestRow());
                foreach (['P', 'Q'] as $col) {
                    for ($r = 8; $r <= $lastRow; $r++) {
                        $c = $sheet->getCell($col.$r);
                        $val = $c->getValue();
                        if (is_string($val) && str_starts_with($val, 'http')) {
                            $c->setValueExplicit($val, DataType::TYPE_STRING);
                            $c->getHyperlink()->setUrl($val);
                            $c->getStyle($col.$r)->getFont()->applyFromArray([
                                'color' => ['argb' => 'FF0F61D0'],
                                'underline' => 'single',
                            ]);
                        }
                    }
                }
            },
        ];
    }
}
