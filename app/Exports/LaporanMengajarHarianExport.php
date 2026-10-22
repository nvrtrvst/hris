<?php

namespace App\Exports;

use App\Models\Jadwal;
use App\Models\Pegawai;
use App\Models\PengajuanIzin;
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
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Detail Mengajar Harian (informatif): 1 baris = 1 pegawai × 1 tanggal,
 * hanya slot mengajar + piket. Hitung jam: seharusnya vs terekam vs kurang
 * (telat memotong jam — masuk dihitung dari jam aktual, bukan jam jadwal).
 */
class LaporanMengajarHarianExport implements FromCollection, ShouldAutoSize, WithCustomStartCell, WithEvents, WithHeadings, WithMapping
{
    use FormulaEscapable;

    /** Hari index Carbon (0=Minggu) → nama hari Jadwal. */
    private const HARI_MAP = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

    private const STATUS_PRIORITY = ['hadir' => 0, 'telat' => 1, 'sakit' => 2, 'izin' => 3, 'cuti' => 4, 'alpa' => 5];

    protected $start_date;

    protected $end_date;

    protected $unit_id;

    protected $jenis;

    protected $search;

    /** @var string[] */
    protected $dates = [];

    /** @var Collection<int, array> baris final */
    protected $rows;

    public function __construct($start_date, $end_date, $unit_id = null, $jenis = null, $search = null)
    {
        $this->start_date = $start_date;
        $this->end_date = $end_date;
        $this->unit_id = $unit_id;
        $this->jenis = $jenis;
        $this->search = $search;

        $cursor = Carbon::parse($start_date);
        $end = Carbon::parse($end_date);
        while ($cursor <= $end) {
            $this->dates[] = $cursor->toDateString();
            $cursor->addDay();
        }

        $this->build();
    }

    private function build(): void
    {
        $start = Carbon::parse($this->start_date);
        $end = Carbon::parse($this->end_date);
        $today = Carbon::today()->toDateString();

        $presensiRows = Presensi::query()
            ->whereBetween('tanggal', [$start->toDateString(), $end->toDateString()])
            ->when($this->unit_id, fn ($q) => $q->where('unit_sekolah_id', $this->unit_id))
            ->when($this->jenis === 'pendidik', fn ($q) => $q->whereHas('pegawai', fn ($p) => $p->guru()))
            ->when($this->jenis === 'kependidikan', fn ($q) => $q->whereHas('pegawai', fn ($p) => $p->nonGuru()))
            ->when($this->search !== null && $this->search !== '', fn ($q) => $q->whereHas('pegawai', fn ($p) => $p->where('nama_lengkap', 'like', '%'.$this->search.'%')))
            ->get([
                'pegawai_id', 'jadwal_id', 'unit_sekolah_id', 'tipe_presensi', 'tanggal',
                'status', 'jam_masuk', 'is_lembur', 'is_tugas_luar',
            ]);

        $presensiByPegDate = $presensiRows->groupBy(fn ($p) => $p->pegawai_id.'|'.($p->tanggal instanceof \DateTimeInterface ? $p->tanggal->format('Y-m-d') : (string) $p->tanggal));

        // Jangkauan bebas presensi: jadwal mengajar + piket dalam rentang.
        $haris = collect($this->dates)->map(fn ($d) => self::HARI_MAP[(int) Carbon::parse($d)->dayOfWeek])->unique()->values()->all();
        $jadwalQuery = Jadwal::query()
            ->with('pegawaiMapel.mataPelajaran')
            ->whereIn('hari', $haris)
            ->whereIn('jenis_jadwal', ['mengajar', 'piket'])
            ->when($this->unit_id, fn ($q) => $q->where('unit_sekolah_id', $this->unit_id))
            ->when($this->jenis === 'pendidik', fn ($q) => $q->whereHas('pegawai', fn ($p) => $p->guru()))
            ->when($this->jenis === 'kependidikan', fn ($q) => $q->whereHas('pegawai', fn ($p) => $p->nonGuru()))
            ->when($this->search !== null && $this->search !== '', fn ($q) => $q->whereHas('pegawai', fn ($p) => $p->where('nama_lengkap', 'like', '%'.$this->search.'%')))
            ->get(['id', 'pegawai_id', 'hari', 'jam_mulai', 'jam_selesai', 'jenis_jadwal', 'unit_sekolah_id', 'kelas_label', 'pegawai_mapel_id']);

        /** @var Collection<string, Collection<int, Jadwal>> kunci pegawai_id|tanggal */
        $slotsByPegDate = collect();
        foreach ($this->dates as $ds) {
            $hari = self::HARI_MAP[(int) Carbon::parse($ds)->dayOfWeek];
            $todaySlots = $jadwalQuery->filter(fn ($j) => $j->hari === $hari);
            foreach ($todaySlots->groupBy('pegawai_id') as $pgId => $slots) {
                $slotsByPegDate[$pgId.'|'.$ds] = $slots->sortBy('jam_mulai')->values();
            }
        }

        $pegawaiIds = $slotsByPegDate->keys()->map(fn ($k) => (int) explode('|', $k)[0])->unique()->values();
        if ($pegawaiIds->isEmpty()) {
            $this->rows = collect();

            return;
        }

        $pegawaiMap = Pegawai::whereIn('id', $pegawaiIds)->with('units:id,nama')->get()->keyBy('id');

        // Izin disetujui yang overlap rentang → map pegawai_id|tanggal => jenis_izin.
        $izinMap = [];
        PengajuanIzin::where('status', 'disetujui')
            ->whereIn('pegawai_id', $pegawaiIds)
            ->where('tanggal_mulai', '<=', $end->endOfDay()->toDateTimeString())
            ->where('tanggal_selesai', '>=', $start->startOfDay()->toDateTimeString())
            ->get(['pegawai_id', 'jenis_izin', 'tanggal_mulai', 'tanggal_selesai'])
            ->each(function ($izin) use (&$izinMap) {
                $mulai = Carbon::parse($izin->tanggal_mulai);
                $selesai = Carbon::parse($izin->tanggal_selesai);
                $cursor = $mulai->copy();
                while ($cursor <= $selesai) {
                    $izinMap[$izin->pegawai_id.'|'.$cursor->toDateString()] = $izin->jenis_izin;
                    $cursor->addDay();
                }
            });

        // Libur (HariLibur via helper rekap + weekend).
        $unitIds = array_values(array_unique(array_merge(
            $this->unit_id ? [$this->unit_id] : [],
            $jadwalQuery->pluck('unit_sekolah_id')->filter()->unique()->all(),
        )));
        $holidayMap = LaporanRekapKehadiranExport::buildHolidayWeekdayMap($unitIds, $start, $end);
        $holidays = array_flip($holidayMap['dates'] ?? []);

        $unitNames = UnitSekolah::whereIn('id', $unitIds)->pluck('nama', 'id');
        $durasiJpByUnit = UnitSekolah::whereIn('id', $unitIds)->pluck('durasi_jp', 'id');

        $rows = collect();
        foreach ($slotsByPegDate as $key => $slots) {
            [$pegawaiId, $ds] = explode('|', $key);
            $dayRows = $presensiByPegDate->get($key, collect());
            $izinType = $izinMap[$key] ?? null;

            $isWeekend = Carbon::parse($ds)->isWeekend();
            $isHoliday = isset($holidays[$ds]) || $isWeekend;
            $isPast = $ds < $today;
            $isToday = $ds === $today;

            $sesiRows = $dayRows->filter(fn ($r) => $r->tipe_presensi === 'mengajar' && ! $r->is_lembur && ! $r->is_tugas_luar && $r->jadwal_id !== null);
            $masukRow = $dayRows
                ->filter(fn ($r) => $r->tipe_presensi === 'kantor' && ! $r->is_lembur && ! $r->is_tugas_luar)
                ->sortBy(fn ($r) => self::STATUS_PRIORITY[$r->status] ?? 9)
                ->first();

            $pegawai = $pegawaiMap->get((int) $pegawaiId);
            $slotsForPeg = $pegawai && $this->unit_id
                ? $slots->filter(fn ($s) => $s->unit_sekolah_id == $this->unit_id)->values() : $slots;

            $sesi = [];
            $plannedMin = 0;
            $masukMin = 0;
            $kurangMin = 0;
            $sesiTelat = 0;
            $sesiMiss = 0;
            foreach ($slotsForPeg as $slot) {
                $slotDur = $this->durasiSlot($slot);
                $plannedMin += $slotDur;
                $plannedJp = $this->jpWeight($slotDur, $durasiJpByUnit, $slot);

                [$state, $jamLabel, $recordedMin] = $this->resolveSlot($slot, $sesiRows, $masukRow, $izinType, $isPast, $isToday);

                $masukJp = $this->jpWeight($recordedMin, $durasiJpByUnit, $slot);
                $masukMin += $recordedMin;

                $kurangJp = 0.0;
                if ($state === 'miss') {
                    $kurangJp = $plannedJp;
                    $kurangMin += $slotDur;
                    $sesiMiss++;
                } elseif ($state === 'ok' || $state === 'telat') {
                    $defisit = max(0, $slotDur - $recordedMin);
                    if ($defisit > 0) {
                        $kurangJp = $this->jpWeight($defisit, $durasiJpByUnit, $slot);
                        $kurangMin += $defisit;
                    }
                    if ($state === 'telat') {
                        $sesiTelat++;
                    }
                }

                $sesi[] = [
                    'jenis' => $slot->jenis_jadwal,
                    'state' => $state,
                    'jam' => $jamLabel,
                    'label' => $this->slotLabel($slot),
                    'planned_jp' => $plannedJp,
                    'masuk_jp' => $masukJp,
                    'kurang_jp' => $kurangJp,
                    'masuk_min' => $recordedMin,
                    'kurang_min' => $state === 'miss' ? $slotDur : (in_array($state, ['ok', 'telat'], true) ? min($slotDur, max(0, $slotDur - $recordedMin)) : 0),
                    'planned_text' => $this->durasiText($slotDur),
                    'masuk_text' => $this->durasiText($recordedMin),
                    'kurang_text' => $this->durasiText($state === 'miss' ? $slotDur : max(0, $slotDur - $recordedMin)),
                ];
            }

            $pegUnitId = $pegawai?->units->firstWhere('id', $this->unit_id)?->id ?? $pegawai?->units->first()?->id;
            $unitLabel = $unitNames[$slotsForPeg->firstWhere('unit_sekolah_id', '!=', null)?->unit_sekolah_id] ?? ($pegawai?->units->first()?->nama ?? '-');

            $rows->push([
                'pegawai_id' => (int) $pegawaiId,
                'tanggal' => $ds,
                'hari' => Carbon::parse($ds)->locale('id')->translatedFormat('l'),
                'nama' => $pegawai?->nama_lengkap ?? ('Pegawai #'.$pegawaiId),
                'unit' => $unitLabel,
                'is_holiday' => $isHoliday,
                'izin' => $izinType,
                'planned_min' => $plannedMin,
                'masuk_min' => $masukMin,
                'kurang_min' => $kurangMin,
                'planned_jp' => round(collect($sesi)->sum('planned_jp'), 2),
                'masuk_jp' => round(collect($sesi)->sum('masuk_jp'), 2),
                'kurang_jp' => round(collect($sesi)->sum('kurang_jp'), 2),
                'sesi_telat' => $sesiTelat,
                'sesi_miss' => $sesiMiss,
                'sesi' => $sesi,
                // pegUnitId dipakai FE untuk guard, tak dipakai Excel.
                'peg_unit_id' => $pegUnitId,
            ]);
        }

        $this->rows = $rows->sortBy([
            ['nama', 'asc'],
            ['tanggal', 'asc'],
        ])->values();
    }

    private function durasiSlot(Jadwal $slot): int
    {
        return max(0, Carbon::parse($slot->jam_mulai)->diffInMinutes(Carbon::parse($slot->jam_selesai)));
    }

    private function jpWeight(int $menit, Collection $durasiJpByUnit, Jadwal $slot): float
    {
        $durasiJp = (int) ($durasiJpByUnit[$slot->unit_sekolah_id] ?? 45);
        if ($durasiJp <= 0) {
            $durasiJp = 45;
        }

        return $menit / $durasiJp;
    }

    private function durasiText(int $menit): string
    {
        if ($menit <= 0) {
            return '0m';
        }
        $j = intdiv($menit, 60);
        $m = $menit % 60;

        return trim(($j > 0 ? $j.'j ' : '').($m > 0 ? $m.'m' : ''));
    }

    /**
     * Chip status per slot mengajar/piket + jam terekam efektif.
     * Return [state ok|telat|miss|izin|'', jam label, recorded menit].
     */
    private function resolveSlot($slot, Collection $sesiRows, ?object $masukRow, ?string $izinType, bool $isPast, bool $isToday): array
    {
        $slotStart = substr($slot->jam_mulai, 0, 5);
        $slotEnd = substr($slot->jam_selesai, 0, 5);

        $row = $sesiRows->firstWhere('jadwal_id', $slot->id);
        if ($row) {
            $state = $row->status === 'telat' ? 'telat' : 'ok';
            $masukJam = $row->jam_masuk ? substr($row->jam_masuk, 0, 5) : $slotStart;
            $recorded = max(0, Carbon::parse($masukJam)->diffInMinutes(Carbon::parse($slotEnd)));

            return [$state, $masukJam, min($recorded, $this->durasiSlot($slot))];
        }

        // Piket: absen kantor hari itu dianggap menutup slot piket.
        if ($slot->jenis_jadwal === 'piket' && $masukRow !== null) {
            $state = $masukRow->status === 'telat' ? 'telat' : 'ok';
            $masukJam = $masukRow->jam_masuk ? substr($masukRow->jam_masuk, 0, 5) : $slotStart;
            $recorded = max(0, Carbon::parse($masukJam)->diffInMinutes(Carbon::parse($slotEnd)));

            return [$state, $masukJam, min($recorded, $this->durasiSlot($slot))];
        }

        if ($izinType) {
            return ['izin', null, 0];
        }

        $selesai = Carbon::parse($slotEnd);
        if ($isPast || ($isToday && $selesai->lt(now()))) {
            return ['miss', $slotStart.'-'.$slotEnd, 0];
        }

        return ['', $slotStart.'-'.$slotEnd, 0];
    }

    private function slotLabel(Jadwal $slot): string
    {
        if ($slot->jenis_jadwal === 'piket') {
            return 'Piket';
        }
        $mapel = $slot->pegawaiMapel?->mataPelajaran?->nama;
        $kelas = $slot->kelas_label ?: null;

        return $mapel ? trim($mapel.($kelas ? ' - '.$kelas : '')) : 'Mengajar';
    }

    public function collection(): Collection
    {
        return $this->rows ?? collect();
    }

    /** Detail sesi → string Excel/PDF, ASCII-safe, 1 sesi per baris dgn tag + selisih. */
    public function aktivitasText(array $sesi, string $sep = "\n"): string
    {
        return implode($sep, array_map(function ($s) {
            $range = ($s['jam'] ?? '') !== null && $s['jam'] !== '' ? $s['jam'] : null;
            $base = $s['label'].($range ? ' ('.$range.', '.$this->jpLabel($s['planned_jp']).')' : '');
            $tag = match ($s['state']) {
                'ok' => 'Hadir ('.$this->durasiText($s['masuk_min'] ?? 0).' masuk)',
                'telat' => 'Telat (kurang '.$this->durasiText($s['kurang_min'] ?? 0).')',
                'miss' => 'Tidak Presensi (kurang '.$this->durasiText($s['kurang_min'] ?? 0).')',
                'izin' => 'Izin',
                default => 'Belum Mulai',
            };

            return trim($base.' - '.$tag);
        }, $sesi));
    }

    private function jpLabel(float $jp): string
    {
        $whole = $jp == (int) $jp;

        return (string) ($whole ? (int) $jp : $jp).' JP';
    }

    public function map($r): array
    {
        $detail = $this->aktivitasText($r['sesi']);

        $row = [
            $r['nama'],
            $r['unit'],
            Carbon::parse($r['tanggal'])->format('d/m/Y'),
            $r['hari'],
            $r['izin'] ? $r['izin'] : '-',
            $this->jpLabel($r['planned_jp']).' ('.$this->durasiText($r['planned_min']).')',
            $r['masuk_min'] > 0 ? $this->jpLabel($r['masuk_jp']).' ('.$this->durasiText($r['masuk_min']).')' : '-',
            $detail !== '' ? $detail : '-',
        ];

        return array_map([self::class, 'escapeFormula'], $row);
    }

    public function headings(): array
    {
        return [
            'Nama Pegawai', 'Unit', 'Tanggal', 'Hari',
            'Status Izin', 'Seharusnya', 'Masuk (Terekam)',
            'Detail Mengajar/Piket',
        ];
    }

    /**
     * Baris detail FE: flat rows (pegawai × tanggal) — FE mengelompokkan per tanggal.
     */
    public function detailData(Collection $rows): array
    {
        return $rows->map(fn ($r) => [
            'tanggal' => $r['tanggal'],
            'hari' => $r['hari'],
            'pegawai_id' => $r['pegawai_id'],
            'nama' => $r['nama'],
            'unit' => $r['unit'],
            'is_holiday' => $r['is_holiday'],
            'izin' => $r['izin'],
            'planned_text' => $this->jpLabel($r['planned_jp']).' ('.$this->durasiText($r['planned_min']).')',
            'masuk_text' => $r['masuk_min'] > 0 ? $this->jpLabel($r['masuk_jp']).' ('.$this->durasiText($r['masuk_min']).')' : '-',
            'kurang_text' => $r['kurang_min'] > 0 ? 'Kurang '.$this->durasiText($r['kurang_min']) : '-',
            'sesi_telat' => $r['sesi_telat'],
            'sesi_miss' => $r['sesi_miss'],
            'sesi' => array_map(fn ($s) => [
                'jenis' => $s['jenis'],
                'state' => $s['state'],
                'jam' => $s['jam'],
                'label' => $s['label'],
                'planned_text' => $this->jpLabel($s['planned_jp']).' ('.$s['planned_text'].')',
                'masuk_text' => $s['masuk_min'] > 0 ? $s['masuk_text'] : '-',
                'kurang_text' => $s['kurang_min'] > 0 ? 'Kurang '.$s['kurang_text'] : '-',
            ], $r['sesi']),
        ])->values()->all();
    }

    public function summaryData(Collection $rows): array
    {
        return [
            'plannedJp' => round($rows->sum('planned_jp'), 2),
            'masukJp' => round($rows->sum('masuk_jp'), 2),
            'kurangJp' => round($rows->sum('kurang_jp'), 2),
            'kurangMin' => $rows->sum('kurang_min'),
            'sesiTelat' => $rows->sum('sesi_telat'),
            'sesiMiss' => $rows->sum('sesi_miss'),
            'izinCount' => $rows->filter(fn ($r) => $r['izin'] !== null)->count(),
        ];
    }

    public function startCell(): string
    {
        return 'A6';
    }

    public function registerEvents(): array
    {
        $lastCol = Coordinate::stringFromColumnIndex(count($this->headings()));

        return [
            AfterSheet::class => function (AfterSheet $event) use ($lastCol) {
                $sheet = $event->sheet->getDelegate();

                $kopUnit = $this->unit_id ? UnitSekolah::find($this->unit_id) : UnitSekolah::where('nama', 'like', 'Yayasan%')->first();
                $namaUnit = $kopUnit?->nama ?? 'Semua Unit Sekolah';
                $periodeStr = Carbon::parse($this->start_date)->format('d/m/Y').' s/d '.Carbon::parse($this->end_date)->format('d/m/Y');

                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->setCellValue('A1', strtoupper($kopUnit?->nama ?? config('yayasan.name')));
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->mergeCells("A2:{$lastCol}2");
                $sheet->setCellValue('A2', 'DETAIL MENGAJAR HARIAN PEGAWAI (MENGAJAR & PIKET)');
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->mergeCells("A3:{$lastCol}3");
                $sheet->setCellValue('A3', 'Periode: '.$periodeStr.' | Unit: '.$namaUnit);
                $sheet->getStyle('A3')->getFont()->setItalic(true);
                $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->mergeCells("A5:{$lastCol}5");
                $sheet->setCellValue('A5', 'Telat memotong jam: "Masuk" dihitung dari jam presensi aktual sampai akhir sesi. Sesi yang jamnya belum terlewati tampil "Belum Mulai".');
                $sheet->getStyle('A5')->getFont()->setSize(9)->setColor(new Color('FF6B7280'));
                $sheet->getStyle('A5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                $sheet->getStyle("A6:{$lastCol}6")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FF0F3D3E'],
                    ],
                ]);
                $sheet->freezePane('A7');

                $lastRow = 6 + ($this->rows?->count() ?? 0);
                $sheet->getStyle("I7:I{$lastRow}")->getAlignment()->setWrapText(true);
            },
        ];
    }
}
