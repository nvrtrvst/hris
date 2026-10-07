<?php

namespace App\Exports;

use App\Models\Presensi;
use App\Models\UnitSekolah;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Rekap presensi mengajar per guru: total periode + breakdown per minggu.
 * Sumber data = presensi per jadwal (finalize-alpa melengkapi yang tak
 * di-slide). Semua count berbobot JP = durasi jadwal ÷ durasi_jp unit,
 * sehingga jadwal blok (multi-JP dalam 1 baris) dihitung beberapa JP.
 */
class LaporanRekapMengajarExport implements FromCollection, ShouldAutoSize, WithCustomStartCell, WithEvents, WithHeadings, WithMapping
{
    use FormulaEscapable;

    protected $start_date;

    protected $end_date;

    protected $unit_id;

    protected $jenis;

    protected $search;

    protected $weeks = [];

    /** Cache rows — collection() dipanggil 2-3x (preview, summary, calendar). */
    private ?Collection $cachedRows = null;

    /** Rows mentah per pegawai (groupBy) — sumber calendarData tanpa query ulang. */
    private Collection $rawRows;

    public function __construct($start_date, $end_date, $unit_id = null, $jenis = null, $search = null)
    {
        $this->start_date = $start_date;
        $this->end_date = $end_date;
        $this->unit_id = $unit_id;
        $this->jenis = $jenis;
        $this->search = $search;

        // Minggu dihitung di constructor (bukan collection) supaya headings()
        // — yang dipanggil Maatwebsite SEBELUM iterasi collection — sudah
        // punya daftar kolom minggu.
        $start = Carbon::parse($start_date);
        $end = Carbon::parse($end_date);
        $cursor = $start->copy()->startOfWeek(Carbon::MONDAY);
        while ($cursor <= $end) {
            $weekStart = $cursor->copy()->max($start);
            $weekEnd = $cursor->copy()->addDays(4)->min($end);
            $monthNames = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            $this->weeks[] = [
                'label' => $weekStart->format('d').'-'.$weekEnd->format('d').' '.$monthNames[(int) $weekEnd->format('m')],
                'start' => $weekStart->toDateString(),
                'end' => $weekEnd->toDateString(),
            ];
            $cursor->addWeek();
        }
    }

    public function collection(): Collection
    {
        if ($this->cachedRows !== null) {
            return $this->cachedRows;
        }

        $weeks = $this->weeks;

        $query = Presensi::with(['pegawai.jabatans', 'pegawai.mapels', 'jadwal.unitSekolah:id,durasi_jp'])
            ->whereBetween('tanggal', [$this->start_date, $this->end_date])
            ->whereNotNull('jadwal_id')
            ->where('tipe_presensi', 'mengajar');

        if ($this->unit_id) {
            $query->where('unit_sekolah_id', $this->unit_id);
        }

        if ($this->jenis === 'pendidik') {
            $query->whereHas('pegawai', fn ($q) => $q->guru());
        } elseif ($this->jenis === 'kependidikan') {
            $query->whereHas('pegawai', fn ($q) => $q->nonGuru());
        }

        if ($this->search !== null && $this->search !== '') {
            $query->whereHas('pegawai', fn ($q) => $q->where('nama_lengkap', 'like', '%'.$this->search.'%'));
        }

        $rows = $query->get([
            'presensi.pegawai_id', 'presensi.jadwal_id',
            'presensi.tanggal', 'presensi.status',
        ]);
        $this->rawRows = $rows->groupBy('pegawai_id');

        // Agregasi per guru + per minggu: JP terjadwal (bobot durasi ÷ durasi_jp
        // unit — jadwal blok dihitung beberapa JP), hadir, telat, alpa.
        // Izin/sakit/cuti pada JP mengajar jarang (blok hari penuh) — tetap
        // dihitung sebagai tidak hadir mengajar.
        $byPegawai = [];
        foreach ($rows as $p) {
            $pid = $p->pegawai_id;
            if (! isset($byPegawai[$pid])) {
                $byPegawai[$pid] = [
                    'pegawai' => $p->pegawai,
                    'total' => ['terjadwal' => 0.0, 'hadir' => 0.0, 'telat' => 0.0, 'alpa' => 0.0],
                    'minggu' => [],
                ];
            }
            $d = &$byPegawai[$pid];

            $w = $this->jpWeight($p);
            $d['total']['terjadwal'] += $w;
            if ($p->status === 'telat') {
                $d['total']['telat'] += $w;
            } elseif ($p->status === 'hadir') {
                $d['total']['hadir'] += $w;
            } elseif ($p->status === 'alpa') {
                $d['total']['alpa'] += $w;
            }

            $tanggal = $p->tanggal->toDateString();
            foreach ($weeks as $i => $w2) {
                if ($tanggal >= $w2['start'] && $tanggal <= $w2['end']) {
                    if (! isset($d['minggu'][$i])) {
                        $d['minggu'][$i] = ['terjadwal' => 0.0, 'hadir' => 0.0, 'telat' => 0.0, 'alpa' => 0.0];
                    }
                    $d['minggu'][$i]['terjadwal'] += $w;
                    if ($p->status === 'telat') {
                        $d['minggu'][$i]['telat'] += $w;
                    } elseif ($p->status === 'hadir') {
                        $d['minggu'][$i]['hadir'] += $w;
                    } elseif ($p->status === 'alpa') {
                        $d['minggu'][$i]['alpa'] += $w;
                    }
                    break;
                }
            }
            unset($d);
        }

        // Pembulatan ditangani output (map/summary/calendar) — hindari dobel.
        return $this->cachedRows = collect(array_values($byPegawai))->sortBy(fn ($d) => $d['pegawai']->nama_lengkap ?? '')->values();
    }

    /**
     * Bobot JP 1 baris presensi = durasi jadwal ÷ durasi_jp unit (default 45).
     * Jadwal dibuat kelipatan durasi_jp (import/generate) → mayoritas integer.
     */
    private function jpWeight($p): float
    {
        $jadwal = $p->jadwal;
        if (! $jadwal) {
            return 0.0;
        }
        $durasiJp = (int) ($jadwal->unitSekolah?->durasi_jp ?? 45);
        if ($durasiJp <= 0) {
            $durasiJp = 45;
        }
        $menit = Carbon::parse($jadwal->jam_mulai)->diffInMinutes(Carbon::parse($jadwal->jam_selesai));

        return round(max(0, $menit) / $durasiJp, 2);
    }

    /**
     * Data kalender untuk grid FE: daftar tanggal periode (weekday) +
     * per guru per tanggal ringkasan {jp, hadir, telat, alpa}.
     * Dipakai preview (controller) — bukan oleh Maatwebsite.
     * Diturunkan dari collection() yang sama (satu query, tanpa SQL mentah
     * yang bergantung dialek driver, tanpa pemangkasan take(500)).
     */
    public function calendarData(): array
    {
        $dates = [];
        $cursor = Carbon::parse($this->start_date);
        $end = Carbon::parse($this->end_date);
        while ($cursor <= $end) {
            if (! $cursor->isWeekend()) {
                $dates[] = $cursor->toDateString();
            }
            $cursor->addDay();
        }

        $cells = [];
        $this->collection(); // isi rawRows
        foreach ($this->rawRows as $pegId => $pegRows) {
            foreach ($pegRows as $p) {
                $w = $this->jpWeight($p);
                $tanggal = $p->tanggal->toDateString();
                if (! isset($cells[$pegId][$tanggal])) {
                    $cells[$pegId][$tanggal] = [0.0, 0.0, 0.0, 0.0];
                }
                $cells[$pegId][$tanggal][0] += $w;
                if ($p->status === 'hadir') {
                    $cells[$pegId][$tanggal][1] += $w;
                } elseif ($p->status === 'telat') {
                    $cells[$pegId][$tanggal][2] += $w;
                } elseif ($p->status === 'alpa') {
                    $cells[$pegId][$tanggal][3] += $w;
                }
            }
        }
        foreach ($cells as $pid => $byDate) {
            foreach ($byDate as $tanggal => $vals) {
                $cells[$pid][$tanggal] = array_map(fn ($v) => $this->numJp($v), $vals);
            }
        }

        $guru = [];
        foreach ($this->collection() as $d) {
            $guru[] = [
                'id' => $d['pegawai']?->id,
                'nama' => $d['pegawai']?->nama_lengkap ?? '-',
                'cells' => $d['pegawai'] ? ($cells[$d['pegawai']->id] ?? []) : [],
            ];
        }

        return [
            'dates' => $dates,
            'guru' => $guru,
        ];
    }

    /**
     * Summary data for frontend preview.
     */
    public function summaryData(): array
    {
        $rows = $this->collection();
        $total = $rows->count();
        $avgPersen = $total > 0
            ? round($rows->sum(fn ($d) => $d['total']['terjadwal'] > 0
                ? (($d['total']['hadir'] + $d['total']['telat']) / $d['total']['terjadwal']) * 100
                : 0) / $total)
            : 0;

        return [
            'totalPegawai' => $total,
            'avgKehadiran' => $avgPersen,
            'totalTerjadwal' => $this->numJp($rows->sum(fn ($d) => $d['total']['terjadwal'])),
            'totalHadir' => $this->numJp($rows->sum(fn ($d) => $d['total']['hadir'])),
            'totalTelat' => $this->numJp($rows->sum(fn ($d) => $d['total']['telat'])),
            'totalAlpa' => $this->numJp($rows->sum(fn ($d) => $d['total']['alpa'])),
        ];
    }

    public function map($d): array
    {
        $terjadwal = (float) $d['total']['terjadwal'];
        $hadirPersen = $terjadwal > 0
            ? round(((($d['total']['hadir'] + $d['total']['telat']) / $terjadwal)) * 100)
            : 0;

        $pegawai = $d['pegawai'];
        $row = [
            $pegawai?->nama_lengkap ?? '-',
            $pegawai?->nuptk ?? '-',
            $pegawai ? $pegawai->jenisPegawaiLabel() : '-',
            $pegawai?->mapels?->pluck('nama')->unique()->implode(', ') ?: '-',
            $this->numJp($d['total']['terjadwal']),
            $this->numJp($d['total']['hadir']),
            $this->numJp($d['total']['telat']),
            $this->numJp($d['total']['alpa']),
            $hadirPersen.'%',
        ];

        // Kolom mingguan: "hadir/terjadwal (telat t)" — telat terlihat eksplisit.
        foreach ($this->weeks as $i => $w) {
            $m = $d['minggu'][$i] ?? null;
            $row[] = $m
                ? sprintf('%s/%s (telat %s)', $this->fmtJp($m['hadir'] + $m['telat']), $this->fmtJp($m['terjadwal']), $this->fmtJp($m['telat']))
                : '-';
        }

        return array_map([self::class, 'escapeFormula'], $row);
    }

    /** Format JP: bulat tampil tanpa desimal, pecahan tampil 2 desimal (teks mingguan). */
    private function fmtJp($value): string
    {
        $v = round((float) $value, 2);

        return fmod($v, 1.0) === 0.0 ? (string) (int) $v : (string) $v;
    }

    /** JP numerik utk sel Excel/JSON: int bila bulat, float 2 desimal bila pecahan. */
    private function numJp($value): int|float
    {
        $v = round((float) $value, 2);

        return fmod($v, 1.0) === 0.0 ? (int) $v : $v;
    }

    public function headings(): array
    {
        $heads = [
            'Nama Guru',
            'NIP',
            'Jenis',
            'Mata Pelajaran',
            'JP Terjadwal',
            'Hadir',
            'Telat',
            'Alpa',
            '% Kehadiran',
        ];

        foreach ($this->weeks ?? [] as $w) {
            $heads[] = $w['label'];
        }

        return $heads;
    }

    public function startCell(): string
    {
        return 'A6';
    }

    public function registerEvents(): array
    {
        $lastCol = chr(ord('A') + 8 + count($this->weeks ?? []));

        return [
            AfterSheet::class => function (AfterSheet $event) use ($lastCol) {
                $sheet = $event->sheet->getDelegate();

                $namaUnit = 'Semua Unit Sekolah';
                $kopUnit = $this->unit_id ? UnitSekolah::find($this->unit_id) : UnitSekolah::where('nama', 'like', 'Yayasan%')->first();
                if ($kopUnit) {
                    $namaUnit = $kopUnit->nama;
                }

                $periodeStr = Carbon::parse($this->start_date)->format('d/m/Y').' s/d '.Carbon::parse($this->end_date)->format('d/m/Y');

                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->setCellValue('A1', strtoupper($kopUnit?->nama ?? config('yayasan.name')));
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->mergeCells("A2:{$lastCol}2");
                $sheet->setCellValue('A2', 'LAPORAN REKAPITULASI PRESENSI MENGAJAR');
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->mergeCells("A3:{$lastCol}3");
                $sheet->setCellValue('A3', 'Periode: '.$periodeStr.' | Unit: '.$namaUnit);
                $sheet->getStyle('A3')->getFont()->setItalic(true);
                $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->getStyle("A6:{$lastCol}6")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FF0F3D3E'],
                    ],
                ]);
            },
        ];
    }
}
