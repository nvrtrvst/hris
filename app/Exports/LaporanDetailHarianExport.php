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
 * Detail Presensi Harian (informatif): 1 baris = 1 pegawai × 1 tanggal.
 * Kolom kunci: Jam Masuk + status (Tepat/Telat/Izin/...), Pulang Kantor
 * (jam / belum / -), dan aktivitas per sesi (mengajar/piket/lembur/
 * tugas luar) dengan tag Hadir/Tak Slide/Izin/Pending.
 */
class LaporanDetailHarianExport implements FromCollection, ShouldAutoSize, WithCustomStartCell, WithEvents, WithHeadings, WithMapping
{
    use FormulaEscapable;

    /** Hari index Carbon (0=Minggu) → nama hari Jadwal. */
    private const HARI_MAP = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

    /** Priority dedup status masuk — paritas rekap agregat. */
    private const STATUS_PRIORITY = ['hadir' => 0, 'telat' => 1, 'sakit' => 2, 'izin' => 3, 'cuti' => 4, 'alpa' => 5];

    protected $start_date;

    protected $end_date;

    protected $unit_id;

    protected $jenis;

    protected $search;

    /** @var string[] */
    protected $dates = [];

    /** @var Collection<int, array> baris detail final */
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

        // Presensi atomik — jangan dedup dulu, perlu tiap baris (kantor/mengajar/lembur/tugas luar).
        $presensiRows = Presensi::query()
            ->whereBetween('tanggal', [$start->toDateString(), $end->toDateString()])
            ->when($this->unit_id, fn ($q) => $q->where('unit_sekolah_id', $this->unit_id))
            ->when($this->jenis === 'pendidik', fn ($q) => $q->whereHas('pegawai', fn ($p) => $p->guru()))
            ->when($this->jenis === 'kependidikan', fn ($q) => $q->whereHas('pegawai', fn ($p) => $p->nonGuru()))
            ->when($this->search !== null && $this->search !== '', fn ($q) => $q->whereHas('pegawai', fn ($p) => $p->where('nama_lengkap', 'like', '%'.$this->search.'%')))
            ->get([
                'pegawai_id', 'jadwal_id', 'unit_sekolah_id', 'tipe_presensi', 'tanggal',
                'status', 'jam_masuk', 'jam_keluar', 'is_lembur', 'lembur_status',
                'is_tugas_luar', 'tugas_luar_status', 'keterangan',
            ]);

        $presensiByPegDate = $presensiRows->groupBy(fn ($p) => $p->pegawai_id.'|'.($p->tanggal instanceof \DateTimeInterface ? $p->tanggal->format('Y-m-d') : (string) $p->tanggal));

        $pegawaiIds = $presensiRows->pluck('pegawai_id')->unique()->values();

        // Jangkauan bebas presensi: jadwal dalam rentang.
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
            // Hanya pegawai-pExtra dalam scope presensi filter? jadwal sudah difilter —
            // gabungkan di bawah via kombinasi pegawai_id & tanggal.
            $todaySlots = $jadwalQuery->filter(fn ($j) => $j->hari === $hari);
            foreach ($todaySlots->groupBy('pegawai_id') as $pgId => $slots) {
                $slotsByPegDate[$pgId.'|'.$ds] = $slots->sortBy('jam_mulai')->values();
            }
        }
        $pegawaiIds = $pegawaiIds->merge($slotsByPegDate->keys()->map(fn ($k) => (int) explode('|', $k)[0]))->unique()->values();

        $pegawaiMap = Pegawai::whereIn('id', $pegawaiIds)->with('units:id,nama')->get()->keyBy('id');

        // Izin disetujui yang overlap rentang → map pegawai_id|tanggal => jenis_izin.
        $izinMap = [];
        if ($pegawaiIds->isNotEmpty()) {
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
        }

        // Libur (HariLibur via helper rekap + weekend).
        $unitIds = array_values(array_unique(array_merge(
            $this->unit_id ? [$this->unit_id] : [],
            $presensiRows->pluck('unit_sekolah_id')->filter()->unique()->all(),
            $jadwalQuery->pluck('unit_sekolah_id')->filter()->unique()->all(),
        )));
        $holidayMap = LaporanRekapKehadiranExport::buildHolidayWeekdayMap($unitIds, $start, $end);
        $holidays = array_flip($holidayMap['dates'] ?? []);

        // Nama unit.
        $unitNames = UnitSekolah::whereIn('id', $unitIds)->pluck('nama', 'id');

        $rows = collect();
        foreach ($pegawaiIds as $pegawaiId) {
            $pegawai = $pegawaiMap->get($pegawaiId);
            $pegUnitId = $pegawai?->units->firstWhere('id', $this->unit_id)?->id ?? $pegawai?->units->first()?->id;

            foreach ($this->dates as $ds) {
                $key = $pegawaiId.'|'.$ds;
                $dayRows = $presensiByPegDate->get($key, collect());
                $slots = $slotsByPegDate->get($key, collect());
                $izinType = $izinMap[$key] ?? null;

                // Skip hari tanpa sinyal: tidak ada presensi, jadwal, izin.
                if ($dayRows->isEmpty() && $slots->isEmpty() && ! $izinType) {
                    continue;
                }

                $isWeekend = Carbon::parse($ds)->isWeekend();
                $isHoliday = isset($holidays[$ds]) || $isWeekend;
                $isPast = $ds < $today;
                $isToday = $ds === $today;

                [$kantor,$lembur,$tugasLuar,$sesiRows] = $this->splitRows($dayRows);

                // Masuk kantor: prioritas status dedup (telat > hadir > ...).
                $masukRow = $kantor->sortBy(fn ($r) => self::STATUS_PRIORITY[$r->status] ?? 9)->first();
                $pulangRow = $kantor->filter(fn ($r) => $r->jam_keluar !== null && $r->jam_keluar !== '')->first();
                if ($pulangRow === null) {
                    $pulangRow = $masukRow;
                }

                [$masukStatus, $masukJam, $pulangJam, $pulangState] = $this->resolveMasukPulang(
                    $masukRow, $pulangRow, $izinType, $isHoliday, $isPast, $isToday,
                    $dayRows->isNotEmpty() || $slots->isNotEmpty(),
                );

                $aktivitas = [];
                foreach ($slots as $slot) {
                    [$state, $jam] = $this->resolveSlot($slot, $sesiRows, $izinType, $masukRow, $isPast, $isToday);
                    $aktivitas[] = [
                        'jenis' => $slot->jenis_jadwal,
                        'label' => $this->slotLabel($slot),
                        'jam' => $jam,
                        'state' => $state,
                    ];
                }
                foreach ($lembur as $lb) {
                    $aktivitas[] = [
                        'jenis' => 'lembur',
                        'label' => 'Lembur',
                        'jam' => $lb->jam_masuk ? substr($lb->jam_masuk, 0, 5) : null,
                        'state' => $lb->lembur_status === 'disetujui' ? 'ok' : ($lb->lembur_status === 'ditolak' ? 'miss' : 'late'),
                    ];
                }
                foreach ($tugasLuar as $tl) {
                    $aktivitas[] = [
                        'jenis' => 'tugas_luar',
                        'label' => 'Tugas Luar',
                        'jam' => $tl->jam_masuk ? substr($tl->jam_masuk, 0, 5) : null,
                        'state' => $tl->tugas_luar_status === 'disetujui' ? 'ok' : ($tl->tugas_luar_status === 'ditolak' ? 'miss' : 'late'),
                    ];
                }

                $firstUnitId = $dayRows->firstWhere('unit_sekolah_id', '!=', null)?->unit_sekolah_id
                    ?? $slots->firstWhere('unit_sekolah_id', '!=', null)?->unit_sekolah_id
                    ?? $pegUnitId;
                $unitLabel = $unitNames[$firstUnitId] ?? ($pegawai?->units->first()?->nama ?? null);

                $rows->push([
                    'pegawai_id' => $pegawaiId,
                    'tanggal' => $ds,
                    'hari' => Carbon::parse($ds)->locale('id')->translatedFormat('l'),
                    'nama' => $pegawai?->nama_lengkap ?? ('Pegawai #'.$pegawaiId),
                    'unit' => $unitLabel ?? '-',
                    'masuk_status' => $masukStatus,  // hadir|telat|sakit|izin|cuti|alpa|libur|''
                    'masuk_jam' => $masukJam,
                    'pulang_jam' => $pulangJam,
                    'pulang_state' => $pulangState, // ''|'tercatat'|'belum'
                    'izin' => $izinType,
                    'aktivitas' => $aktivitas,
                ]);
            }
        }

        $this->rows = $rows->sortBy([
            ['nama', 'asc'],
            ['tanggal', 'asc'],
        ])->values();
    }

    /**
     * Split raw day presensi rows: kantor vs lembur vs tugas luar vs sesi mengajar.
     *
     * @return array{0: Collection, 1: Collection, 2: Collection, 3: Collection<Jadwal>}
     */
    private function splitRows(Collection $dayRows): array
    {
        return [
            $dayRows->filter(fn ($r) => $r->tipe_presensi === 'kantor' && ! $r->is_lembur && ! $r->is_tugas_luar),
            $dayRows->filter(fn ($r) => $r->is_lembur),
            $dayRows->filter(fn ($r) => $r->is_tugas_luar && ! $r->is_lembur),
            $dayRows->filter(fn ($r) => $r->tipe_presensi === 'mengajar' && ! $r->is_lembur && ! $r->is_tugas_luar && $r->jadwal_id !== null),
        ];
    }

    /**
     * Resolve status masuk & pulang kantor.
     * Pola: bila ada kantor row, status dari row (telat → Telat, hadir → Tepat),
     * izin → label, libur → Libur, hari kerja lampau tanpa record → Alpa,
     * masih/future → '' (netral).
     *
     * @return array{0: string, 1: ?string, 2: ?string, 3: string}
     */
    private function resolveMasukPulang($masukRow, $pulangRow, ?string $izinType, bool $isHoliday, bool $isPast, bool $isToday, bool $hasAnyRow): array
    {
        if ($masukRow !== null) {
            $status = $masukRow->status;
            $jam = $masukRow->jam_masuk ? substr($masukRow->jam_masuk, 0, 5) : null;

            if ($pulangRow && $pulangRow->jam_keluar) {
                $pulangState = 'tercatat';
            } elseif ($isToday || $isPast) {
                $pulangState = 'belum';
            } else {
                $pulangState = '';
            }

            return [$status, $jam, $pulangRow?->jam_keluar ? substr($pulangRow->jam_keluar, 0, 5) : null, $pulangState];
        }

        // Tidak ada presensi kantor.
        if ($izinType) {
            return [$izinType === 'cuti' ? 'cuti' : ($izinType === 'sakit' ? 'sakit' : 'izin'), null, null, ''];
        }
        if ($isHoliday) {
            return ['libur', null, null, ''];
        }
        if ($isPast && $hasAnyRow) {
            return ['alpa', null, null, ''];
        }

        return ['', null, null, ''];
    }

    /**
     * Chip status per slot jadwal.
     * Return [state ok|telat|miss|izin|late|'', jam label].
     */
    private function resolveSlot($slot, Collection $sesiRows, ?string $izinType, $masukRow, bool $isPast, bool $isToday): array
    {
        $row = $sesiRows->firstWhere('jadwal_id', $slot->id);
        if ($row) {
            $state = $row->status === 'telat' ? 'telat' : 'ok';

            return [$state, $row->jam_masuk ? substr($row->jam_masuk, 0, 5) : substr($row->jam_selesai, 0, 5)];
        }

        // Piket: absen kantor hari itu dianggap menutup slot piket.
        if ($slot->jenis_jadwal === 'piket' && $masukRow !== null) {
            $state = $masukRow->status === 'telat' ? 'telat' : 'ok';

            return [$state, $masukRow->jam_masuk ? substr($masukRow->jam_masuk, 0, 5) : null];
        }

        if ($izinType) {
            return ['izin', null];
        }

        $jamSelesai = substr($slot->jam_selesai, 0, 5);
        if ($isPast || ($isToday && Carbon::parse($jamSelesai)->lt(now()))) {
            return ['miss', substr($slot->jam_mulai, 0, 5).'-'.$jamSelesai];
        }

        return ['', substr($slot->jam_mulai, 0, 5).'-'.$jamSelesai];
    }

    private function slotLabel(Jadwal $slot): string
    {
        $mapel = $slot->pegawaiMapel?->mataPelajaran?->nama;
        $kelas = $slot->kelas_label ?: null;

        return $mapel ? trim($mapel.($kelas ? ' - '.$kelas : '')) : 'Mengajar';
    }

    /** Label status masuk untuk Excel & PDF: telat ditandai, tepat = jam saja. */
    public static function statusLabel(string $status, ?string $jam): string
    {
        return match ($status) {
            'hadir' => $jam ?: '-',
            'telat' => 'Telat ('.$jam.')',
            'sakit' => 'Sakit',
            'izin' => 'Izin',
            'cuti' => 'Cuti',
            'alpa' => 'Alpa',
            'libur' => 'Libur',
            default => '-',
        };
    }

    /** Aktivitas → string Excel/PDF, ASCII-safe, 1 aktivitas per baris (tag Hadir/Telat/Tak Slide/Izin/Pending). */
    public function aktivitasText(array $aktivitas, string $sep = "\n"): string
    {
        $tags = ['ok' => 'Hadir', 'telat' => 'Telat', 'miss' => 'Tak Slide', 'izin' => 'Izin', 'late' => 'Pending'];

        return implode($sep, array_map(function ($a) use ($tags) {
            $tag = $tags[$a['state']] ?? '';

            return trim($a['label'].($a['jam'] ? ' ('.$a['jam'].')' : '').($tag ? ' - '.$tag : '')) ?: '-';
        }, $aktivitas));
    }

    public function collection(): Collection
    {
        return $this->rows ?? collect();
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
            'masuk_status' => $r['masuk_status'],
            'masuk_jam' => $r['masuk_jam'],
            'izin' => $r['izin'],
            'pulang_jam' => $r['pulang_jam'],
            'pulang_state' => $r['pulang_state'],
            'aktivitas' => $r['aktivitas'],
        ])->values()->all();
    }

    public function summaryData(Collection $rows): array
    {
        return [
            'telat' => $rows->filter(fn ($r) => $r['masuk_status'] === 'telat')->count(),
            'takSlide' => $rows->sum(fn ($r) => collect($r['aktivitas'])->where('state', 'miss')->count()),
            'belumPulang' => $rows->filter(fn ($r) => $r['pulang_state'] === 'belum')->count(),
            'izinCount' => $rows->filter(fn ($r) => in_array($r['masuk_status'], ['izin', 'sakit', 'cuti']))->count(),
        ];
    }

    public function map($r): array
    {
        $aktivitasText = $this->aktivitasText($r['aktivitas']);

        $row = [
            $r['nama'],
            $r['unit'],
            Carbon::parse($r['tanggal'])->format('d/m/Y'),
            $r['hari'],
            self::statusLabel($r['masuk_status'], $r['masuk_jam']),
            $r['pulang_jam'] ?: ($r['pulang_state'] === 'belum' ? 'Tidak Presensi Pulang' : '-'),
            $aktivitasText !== '' ? $aktivitasText : '-',
        ];

        return array_map([self::class, 'escapeFormula'], $row);
    }

    public function headings(): array
    {
        return [
            'Nama Pegawai', 'Unit', 'Tanggal', 'Hari',
            'Jam Masuk & Status', 'Pulang Kantor', 'Aktivitas (Mengajar/Piket/Lembur/Tugas Luar)',
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
                $sheet->setCellValue('A2', 'DETAIL PRESENSI HARIAN PEGAWAI');
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->mergeCells("A3:{$lastCol}3");
                $sheet->setCellValue('A3', 'Periode: '.$periodeStr.' | Unit: '.$namaUnit);
                $sheet->getStyle('A3')->getFont()->setItalic(true);
                $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->mergeCells("A5:{$lastCol}5");
                $sheet->setCellValue('A5', 'Aktivitas: tag (Hadir/Tak Slide/Izin/Pending). Telat = masuk kantor lewat jam, sisanya tampil jam masuk saja. "Tidak Presensi Pulang" = belum absen keluar.');
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

                // Kolom aktivitas panjang — wrap biar rapih, bukan overflow.
                $lastRow = 6 + ($this->rows?->count() ?? 0);
                $sheet->getStyle("G7:G{$lastRow}")->getAlignment()->setWrapText(true);
            },
        ];
    }
}
