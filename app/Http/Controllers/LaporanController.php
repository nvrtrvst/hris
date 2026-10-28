<?php

namespace App\Http\Controllers;

use App\Constants\BobotKehadiran;
use App\Constants\PresensiMessages;
use App\Exceptions\PdfTooLargeException;
use App\Exports\LaporanDetailHarianExport;
use App\Exports\LaporanLemburanExport;
use App\Exports\LaporanMengajarHarianExport;
use App\Exports\LaporanPenggajianExport;
use App\Exports\LaporanPresensiExport;
use App\Exports\LaporanRekapKehadiranExport;
use App\Exports\LaporanRekapKehadiranHarianExport;
use App\Exports\LaporanRekapMengajarExport;
use App\Http\Requests\KcdReportRequest;
use App\Http\Requests\LaporanGenerateRequest;
use App\Models\AuditLog;
use App\Models\Jadwal;
use App\Models\LaporanKcdCetak;
use App\Models\LaporanPiket;
use App\Models\Pegawai;
use App\Models\PengajuanIzin;
use App\Models\PiketArea;
use App\Models\PiketPos;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Services\KcdReportService;
use App\Services\LaporanPdfService;
use App\Services\PiketIzinService;
use App\Services\PiketService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class LaporanController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $isAdmin = $user && ($user->can('view_dashboard') || $user->can('view_payroll') || $user->isPayrollOperator());
        if (! $isAdmin) {
            abort(403, 'Akses Ditolak. Anda tidak memiliki izin melihat modul laporan.');
        }

        if ($user && $user->can('view_all_units')) {
            $units = UnitSekolah::all();
        } elseif ($user && $user->isPayrollOperator()) {
            $units = UnitSekolah::whereIn('id', $user->payrollUnitIds())->get();
        } elseif ($user && $user->unit_sekolah_id) {
            $units = UnitSekolah::where('id', $user->unit_sekolah_id)->get();
        } else {
            $units = UnitSekolah::all();
        }

        return Inertia::render('Laporan/Index', [
            'units' => $units,
        ]);
    }

    public function preview(LaporanGenerateRequest $request)
    {
        $validated = $request->validated();
        $unitId = $this->resolveEffectiveUnitId($validated['unit_sekolah_id'] ?? null);

        $jenis = $validated['jenis_filter'] ?? null;
        $tipe = $validated['tipe_filter'] ?? null;
        $search = $validated['search'] ?? null;
        $status = $validated['status_filter'] ?? null;
        $export = null;
        if ($validated['type'] === 'presensi') {
            $export = new LaporanPresensiExport($validated['start_date'], $validated['end_date'], $unitId, $jenis, $tipe, $search, $status, $validated['jadwal_filter'] ?? null);
        } elseif ($validated['type'] === 'penggajian') {
            $export = new LaporanPenggajianExport($validated['start_date'], $validated['end_date'], $unitId, $jenis, $search);
        } elseif ($validated['type'] === 'lemburan') {
            $export = new LaporanLemburanExport($validated['start_date'], $validated['end_date'], $unitId, $jenis, $search, $validated['lembur_status'] ?? 'semua');
        } elseif ($validated['type'] === 'rekap_mengajar') {
            $export = new LaporanRekapMengajarExport($validated['start_date'], $validated['end_date'], $unitId, $jenis, $search);
        } elseif ($validated['type'] === 'rekap_kehadiran') {
            $export = new LaporanRekapKehadiranExport($validated['start_date'], $validated['end_date'], $unitId, $jenis, $search, $status);
        } elseif ($validated['type'] === 'rekap_kehadiran_harian') {
            $export = new LaporanRekapKehadiranHarianExport($validated['start_date'], $validated['end_date'], $unitId, $jenis, $search, $status);
        } elseif ($validated['type'] === 'detail_harian') {
            $export = new LaporanDetailHarianExport($validated['start_date'], $validated['end_date'], $unitId, $jenis, $search);
        } elseif ($validated['type'] === 'mengajar_harian') {
            $export = new LaporanMengajarHarianExport($validated['start_date'], $validated['end_date'], $unitId, $jenis, $search);
        }

        if (! $export) {
            return response()->json(['error' => 'Invalid type'], 400);
        }

        $all = $export->collection();
        $total = $all->count();
        $data = $all->take(500);
        $headings = $export->headings();

        $mappedData = $data->map(function ($item) use ($export) {
            return $export->map($item);
        });

        $payload = [
            'headings' => $headings,
            'data' => $mappedData,
            'total' => $total,
        ];

        if ($validated['type'] === 'presensi') {
            $counts = $all->countBy('status');
            $hadir = $counts->get('hadir', 0);
            $telat = $counts->get('telat', 0);
            $hadirTelat = $hadir + $telat;
            $payload['summary'] = [
                'total' => $total,
                'hadir' => $hadir,
                'telat' => $telat,
                'izin' => $counts->get('izin', 0),
                'sakit' => $counts->get('sakit', 0),
                'cuti' => $counts->get('cuti', 0),
                'alpa' => $counts->get('alpa', 0),
                'present' => $hadirTelat,
                'persen' => $total > 0 ? (int) round($hadirTelat * 100 / $total) : 0,
            ];
        }

        // Khusus rekap mengajar: sertakan data kalender (guru × tanggal)
        // untuk tampilan grid mingguan di frontend.
        if ($validated['type'] === 'rekap_mengajar') {
            $payload['calendar'] = $export->calendarData();
            $payload['summary'] = $export->summaryData();
            $payload['pegawai_ids'] = $data->pluck('pegawai.id')->values();
        }

        // Khusus rekap kehadiran: sertakan summary stats + pegawai IDs untuk drill-down.
        if ($validated['type'] === 'rekap_kehadiran') {
            $payload['summary'] = $export->summaryData();
            $payload['pegawai_ids'] = $data->pluck('pegawai.id')->values();
        }

        // Matriks kehadiran harian: sel per pegawai per tanggal + summary.
        if ($validated['type'] === 'rekap_kehadiran_harian') {
            $payload['matrix'] = $export->matrixData($data);
            $payload['summary'] = $export->summaryData();
            $payload['pegawai_ids'] = $data->pluck('pegawai.id')->values();
        }

        // Detail Presensi Harian: rows flat (pegawai × tanggal) + summary.
        if ($validated['type'] === 'detail_harian') {
            $payload['detail'] = $export->detailData($data);
            $payload['summary'] = $export->summaryData($data);
        }

        // Detail Mengajar Harian: rows flat + perhitungan jam sesi.
        if ($validated['type'] === 'mengajar_harian') {
            $payload['detail'] = $export->detailData($data);
            $payload['summary'] = $export->summaryData($data);
        }

        return response()->json($payload);
    }

    public function exportPresensi(LaporanGenerateRequest $request)
    {
        $validated = $request->validated();
        $unitId = $this->resolveEffectiveUnitId($validated['unit_sekolah_id'] ?? null);
        $unitSlug = $this->resolveUnitSlug($unitId);

        return Excel::download(
            new LaporanPresensiExport($validated['start_date'], $validated['end_date'], $unitId, $validated['jenis_filter'] ?? null, $validated['tipe_filter'] ?? null, $validated['search'] ?? null, $validated['status_filter'] ?? null, $validated['jadwal_filter'] ?? null),
            'Laporan_Presensi_'.$unitSlug.'_'.$validated['start_date'].'_to_'.$validated['end_date'].'.xlsx'
        );
    }

    public function exportRekapMengajar(LaporanGenerateRequest $request)
    {
        $validated = $request->validated();
        $unitId = $this->resolveEffectiveUnitId($validated['unit_sekolah_id'] ?? null);
        $unitSlug = $this->resolveUnitSlug($unitId);

        return Excel::download(
            new LaporanRekapMengajarExport($validated['start_date'], $validated['end_date'], $unitId, $validated['jenis_filter'] ?? null, $validated['search'] ?? null),
            'Laporan_Rekap_Mengajar_'.$unitSlug.'_'.$validated['start_date'].'_to_'.$validated['end_date'].'.xlsx'
        );
    }

    public function exportRekapKehadiran(LaporanGenerateRequest $request)
    {
        $validated = $request->validated();
        $unitId = $this->resolveEffectiveUnitId($validated['unit_sekolah_id'] ?? null);
        $unitSlug = $this->resolveUnitSlug($unitId);

        return Excel::download(
            new LaporanRekapKehadiranExport($validated['start_date'], $validated['end_date'], $unitId, $validated['jenis_filter'] ?? null, $validated['search'] ?? null, $validated['status_filter'] ?? null),
            'Laporan_Rekap_Kehadiran_'.$unitSlug.'_'.$validated['start_date'].'_to_'.$validated['end_date'].'.xlsx'
        );
    }

    public function exportRekapKehadiranHarian(LaporanGenerateRequest $request)
    {
        $validated = $request->validated();
        $unitId = $this->resolveEffectiveUnitId($validated['unit_sekolah_id'] ?? null);
        $unitSlug = $this->resolveUnitSlug($unitId);

        return Excel::download(
            new LaporanRekapKehadiranHarianExport($validated['start_date'], $validated['end_date'], $unitId, $validated['jenis_filter'] ?? null, $validated['search'] ?? null, $validated['status_filter'] ?? null),
            'Laporan_Rekap_Kehadiran_Harian_'.$unitSlug.'_'.$validated['start_date'].'_to_'.$validated['end_date'].'.xlsx'
        );
    }

    public function exportDetailHarian(LaporanGenerateRequest $request)
    {
        $validated = $request->validated();
        $unitId = $this->resolveEffectiveUnitId($validated['unit_sekolah_id'] ?? null);
        $unitSlug = $this->resolveUnitSlug($unitId);

        return Excel::download(
            new LaporanDetailHarianExport($validated['start_date'], $validated['end_date'], $unitId, $validated['jenis_filter'] ?? null, $validated['search'] ?? null),
            'Laporan_Detail_Presensi_Harian_'.$unitSlug.'_'.$validated['start_date'].'_to_'.$validated['end_date'].'.xlsx'
        );
    }

    public function exportMengajarHarian(LaporanGenerateRequest $request)
    {
        $validated = $request->validated();
        $unitId = $this->resolveEffectiveUnitId($validated['unit_sekolah_id'] ?? null);
        $unitSlug = $this->resolveUnitSlug($unitId);

        return Excel::download(
            new LaporanMengajarHarianExport($validated['start_date'], $validated['end_date'], $unitId, $validated['jenis_filter'] ?? null, $validated['search'] ?? null),
            'Laporan_Detail_Mengajar_Harian_'.$unitSlug.'_'.$validated['start_date'].'_to_'.$validated['end_date'].'.xlsx'
        );
    }

    public function exportPenggajian(LaporanGenerateRequest $request)
    {
        $validated = $request->validated();
        $unitId = $this->resolveEffectiveUnitId($validated['unit_sekolah_id'] ?? null);
        $unitSlug = $this->resolveUnitSlug($unitId);

        return Excel::download(
            new LaporanPenggajianExport($validated['start_date'], $validated['end_date'], $unitId, $validated['jenis_filter'] ?? null, $validated['search'] ?? null),
            'Laporan_Rekap_Gaji_'.$unitSlug.'_'.$validated['start_date'].'_to_'.$validated['end_date'].'.xlsx'
        );
    }

    public function exportLemburan(LaporanGenerateRequest $request)
    {
        $validated = $request->validated();
        $unitId = $this->resolveEffectiveUnitId($validated['unit_sekolah_id'] ?? null);
        $unitSlug = $this->resolveUnitSlug($unitId);

        return Excel::download(
            new LaporanLemburanExport($validated['start_date'], $validated['end_date'], $unitId, $validated['jenis_filter'] ?? null, $validated['search'] ?? null, $validated['lembur_status'] ?? 'semua'),
            'Laporan_Lemburan_Potongan_'.$unitSlug.'_'.$validated['start_date'].'_to_'.$validated['end_date'].'.xlsx'
        );
    }

    /**
     * Export PDF sync. Guard jumlah baris + memory limit di LaporanPdfService —
     * di atas guard, tolak dengan 422 dan sarankan Excel.
     */
    public function exportPdf(LaporanGenerateRequest $request)
    {
        $validated = $request->validated();
        $unitId = $this->resolveEffectiveUnitId($validated['unit_sekolah_id'] ?? null);

        try {
            $result = app(LaporanPdfService::class)->generate($validated, $unitId);

            return response($result['content'], 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$result['filename'].'"',
                'Content-Length' => strlen($result['content']),
            ]);
        } catch (PdfTooLargeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            \Log::error('PDF laporan gagal', [
                'type' => $validated['type'] ?? null,
                'message' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'PDF gagal dibuat: '.substr($e->getMessage(), 0, 300),
            ], 500);
        }
    }

    /**
     * Detail kehadiran 1 pegawai dalam periode tertentu (drill-down dari rekap).
     */
    public function rekapDetail(Request $request)
    {
        $request->validate([
            'pegawai_id' => 'required|exists:pegawai,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $pegawai = Pegawai::with(['jabatans', 'units'])->findOrFail($request->pegawai_id);

        $rows = Presensi::with(['jadwal', 'unitSekolah'])
            ->where('pegawai_id', $pegawai->id)
            ->whereBetween('tanggal', [$request->start_date, $request->end_date])
            ->where('is_lembur', false)
            ->get();

        // 1 pegawai per tanggal = 1 status — aturan sama dengan rekap agregat,
        // supaya rincian tidak pernah beda hitungan dengan rekap.
        $rows = LaporanRekapKehadiranExport::dedupStatusPerTanggal($rows)
            ->sortBy(fn ($p) => $p->tanggal->format('Y-m-d'))
            ->values();

        $detail = $rows->map(fn ($p) => [
            'tanggal' => $p->tanggal->format('Y-m-d'),
            'hari' => $p->tanggal->locale('id')->translatedFormat('l'),
            'status' => $p->status,
            'jam_masuk' => $p->jam_masuk,
            'jam_keluar' => $p->jam_keluar,
            'keterangan' => $p->keterangan,
            'tipe_presensi' => $p->tipe_presensi,
            'unit' => $p->unitSekolah?->nama ?? '-',
        ]);

        $summary = [
            'hadir' => $rows->where('status', 'hadir')->count(),
            'telat' => $rows->where('status', 'telat')->count(),
            'sakit' => $rows->where('status', 'sakit')->count(),
            'izin' => $rows->where('status', 'izin')->count(),
            'cuti' => $rows->where('status', 'cuti')->count(),
        ];

        // Alpa = hari kerja tanpa record — rumus sama dengan rekap agregat & payroll.
        $unitId = $pegawai->unitUtama()?->id;
        $unitIds = array_values(array_unique(array_filter(array_merge(
            $unitId ? [$unitId] : [],
            $rows->pluck('unit_sekolah_id')->filter()->unique()->all(),
        ))));
        $start = Carbon::parse($request->start_date);
        $end = Carbon::parse($request->end_date);
        $holidayMap = LaporanRekapKehadiranExport::buildHolidayWeekdayMap($unitIds, $start, $end);
        $unitUtama = $pegawai->unitUtama();
        $hariKerja = LaporanRekapKehadiranExport::computeUniformWorkingDays(
            $start,
            $end,
            $holidayMap,
            $unitId ? (int) $unitId : null,
            $unitUtama ? Pegawai::hariKerjaUnit($unitUtama) : null,
        );

        $summary['alpa'] = max(0, $hariKerja - $summary['hadir'] - $summary['telat'] - $summary['sakit'] - $summary['izin'] - $summary['cuti']);
        $summary['hariKerja'] = $hariKerja;

        // Persen berbobot (sama dengan rekap agregat): sakit surat-tanpa-surat
        // dibelah via pengajuan izin sakit berflag surat dokter.
        $sakitDates = $rows->where('status', 'sakit')
            ->map(fn ($p) => $p->tanggal->format('Y-m-d'));
        $sakitRanges = BobotKehadiran::sakitRanges([$pegawai->id], $start, $end)->get($pegawai->id, []);
        $split = BobotKehadiran::splitSakit($sakitDates, $sakitRanges, $unitUtama?->sakitWajibTugas() ?? false);
        $summary['sakit_surat'] = $split['surat'];
        $summary['sakit_tanpa_surat'] = $split['tanpa'];
        $bobot = $pegawai->unitUtama()?->bobotHadiran() ?? BobotKehadiran::DEFAULTS;
        $summary['persen'] = BobotKehadiran::persenBerbobot(
            BobotKehadiran::hadirEq($summary['hadir'], $summary['telat'], $split['surat'], $split['tanpa'], $summary['izin'], 0, $summary['cuti'], $bobot),
            $hariKerja
        );

        $periode = [
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
        ];

        return Inertia::render('Laporan/RekapDetail', [
            'pegawai' => [
                'id' => $pegawai->id,
                'nama' => $pegawai->nama_lengkap,
                'nuptk' => $pegawai->nuptk,
                'jenis' => $pegawai->jenisPegawaiLabel(),
            ],
            'detail' => $detail,
            'summary' => $summary,
            'periode' => $periode,
        ]);
    }

    /**
     * Detail mengajar 1 pegawai dalam periode tertentu (drill-down dari rekap mengajar).
     */
    public function rekapMengajarDetail(Request $request)
    {
        $request->validate([
            'pegawai_id' => 'required|exists:pegawai,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $pegawai = Pegawai::with(['jabatans', 'units', 'mapels'])->findOrFail($request->pegawai_id);

        $rows = Presensi::with(['jadwal.pegawaiMapel.mataPelajaran', 'jadwal.unitSekolah:id,durasi_jp', 'unitSekolah'])
            ->where('pegawai_id', $pegawai->id)
            ->whereBetween('tanggal', [$request->start_date, $request->end_date])
            ->whereNotNull('jadwal_id')
            ->where('tipe_presensi', 'mengajar')
            ->orderBy('tanggal')
            ->orderByRaw('COALESCE(jam_masuk, "23:59") asc')
            ->get();

        // Bobot JP per baris: durasi jadwal ÷ durasi_jp unit (paritas dengan
        // LaporanRekapMengajarExport::jpWeight — jadwal blok = beberapa JP).
        $jpWeight = function ($p): float {
            $durasiJp = (int) ($p->jadwal?->unitSekolah?->durasi_jp ?? 45);
            if ($durasiJp <= 0) {
                $durasiJp = 45;
            }
            $menit = Carbon::parse($p->jadwal?->jam_mulai)->diffInMinutes(Carbon::parse($p->jadwal?->jam_selesai));

            return round(max(0, $menit) / $durasiJp, 2);
        };

        $detail = $rows->map(fn ($p) => [
            'tanggal' => $p->tanggal->format('Y-m-d'),
            'hari' => $p->tanggal->locale('id')->translatedFormat('l'),
            'mapel' => $p->jadwal?->mataPelajaran?->nama ?? '-',
            'kelas' => $p->jadwal?->kelas_label ?? '-',
            'jam_mulai' => $p->jam_masuk,
            'jam_selesai' => $p->jam_keluar,
            'jp' => $jpWeight($p),
            'status' => $p->status,
            'unit' => $p->unitSekolah?->nama ?? '-',
        ]);

        $summary = [
            'terjadwal' => round((float) $rows->sum($jpWeight), 2),
            'hadir' => round((float) $rows->where('status', 'hadir')->sum($jpWeight), 2),
            'telat' => round((float) $rows->where('status', 'telat')->sum($jpWeight), 2),
            'alpa' => round((float) $rows->where('status', 'alpa')->sum($jpWeight), 2),
        ];

        $periode = [
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
        ];

        return Inertia::render('Laporan/RekapMengajarDetail', [
            'pegawai' => [
                'id' => $pegawai->id,
                'nama' => $pegawai->nama_lengkap,
                'nuptk' => $pegawai->nuptk,
                'jenis' => $pegawai->jenisPegawaiLabel(),
                'mapels' => $pegawai->mapels->pluck('nama')->implode(', '),
            ],
            'detail' => $detail,
            'summary' => $summary,
            'periode' => $periode,
        ]);
    }

    /**
     * Buka jalur remedial: item seksi Pagi boleh dicatat telat atas
     * pengajuan pegawai (via WA/admin). Hanya laporan draft.
     */
    public function unlockPiket(Request $request, LaporanPiket $laporan)
    {
        $user = $request->user();
        $unitId = $laporan->unit_sekolah_id;

        if (! $user->can('manage_master_data') && (int) $user->unit_sekolah_id !== (int) $unitId) {
            abort(403, 'Akses ditolak.');
        }

        if ($laporan->status === LaporanPiket::STATUS_SUBMITTED) {
            return back()->withErrors(['laporan' => 'Laporan sudah dikirim, tidak bisa dibuka.']);
        }

        $laporan->forceFill([
            'unlocked_at' => now(),
            'unlocked_by' => $user->id,
        ])->save();

        AuditLog::log($laporan, 'unlock_piket', null, [
            'unlocked_by' => $user->id,
            'tanggal' => $laporan->tanggal?->toDateString(),
        ]);

        return back()->with('message', 'Jalur telat dibuka untuk laporan piket ini.');
    }

    public function kcdIndex()
    {
        $user = request()->user();

        $units = $user->can('view_all_units')
            ? UnitSekolah::orderBy('nama')->get(['id', 'nama'])
            : UnitSekolah::where('id', $user->unit_sekolah_id)->get(['id', 'nama']);

        return Inertia::render('Laporan/Kcd', [
            'auth' => [
                'user' => $user->only(['id', 'name', 'email']),
                'permissions' => $user->getAllPermissions()->pluck('name'),
                'roles' => $user->getRoleNames(),
            ],
            'units' => $units,
        ]);
    }

    public function kcdPreview(KcdReportRequest $request)
    {
        $validated = $request->validated();

        $unit = UnitSekolah::findOrFail($validated['unit_sekolah_id']);
        $data = (new KcdReportService)->build($unit, $validated['periode'], $validated['minggu'] ?? null);

        if (request()->user()->can('view_all_units')) {
            $data['unit']['logo_path'] = $this->resolveYayasanLogoPath() ?? $data['unit']['logo_path'];
        }

        return response()->json($data);
    }

    public function kcdPdf(KcdReportRequest $request)
    {
        $validated = $request->validated();
        $minggu = $validated['minggu'] ?? null;

        $unit = UnitSekolah::findOrFail($validated['unit_sekolah_id']);
        $data = (new KcdReportService)->build($unit, $validated['periode'], $minggu);

        if (request()->user()->can('view_all_units')) {
            $data['unit']['logo_path'] = $this->resolveYayasanLogoPath() ?? $data['unit']['logo_path'];
        }

        $period = Carbon::parse($validated['periode'].'-01');
        $start = $period->copy()->startOfMonth()->toDateString();
        $end = $period->copy()->endOfMonth()->toDateString();

        $nomorCetak = LaporanKcdCetak::where('unit_sekolah_id', $unit->id)
            ->where('periode_key', $validated['periode'])
            ->where('minggu', $minggu)
            ->count() + 1;

        LaporanKcdCetak::create([
            'user_id' => request()->user()->id,
            'unit_sekolah_id' => $unit->id,
            'periode_key' => $validated['periode'],
            'minggu' => $minggu,
            'start_date' => $start,
            'end_date' => $end,
            'nomor_cetak' => $nomorCetak,
        ]);

        $data['nomor_cetak'] = $nomorCetak;
        $data['download_at'] = now()->format('d/m/Y H:i');
        $data['download_by'] = request()->user()->name;

        $suffix = $minggu ? '_M'.$minggu : '';

        $pdf = Pdf::loadView('exports.pdf-kcd', $data)
            ->setPaper('A4', 'landscape');

        return $pdf->download('Laporan_KCD_'.$unit->singkatan.'_'.$validated['periode'].$suffix.'.pdf');
    }

    /**
     * Resolve logo unit ke path file lokal untuk DOMPDF. Null bila unit
     * tidak punya logo — caller fallback ke kop config.
     */
    protected function resolveUnitLogoPath(?UnitSekolah $unit): ?string
    {
        return app(LaporanPdfService::class)->resolveUnitLogoPath($unit);
    }

    /**
     * Resolve logo yayasan ke path file lokal untuk DOMPDF.
     */
    protected function resolveYayasanLogoPath(): ?string
    {
        return app(LaporanPdfService::class)->resolveYayasanLogoPath();
    }

    private function resolveUnitSlug(?int $unitId): string
    {
        return app(LaporanPdfService::class)->resolveUnitSlug($unitId);
    }

    /**
     * Resolve unit ID: request value > user scope > null.
     * Superadmin (view_all_units) without explicit request → null (all units).
     */
    private function resolveEffectiveUnitId(?int $requestUnitId): ?int
    {
        if ($requestUnitId) {
            return $requestUnitId;
        }

        $user = auth()->user();
        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
            return $user->unit_sekolah_id;
        }

        return null;
    }

    /**
     * Buat pengajuan izin dari entry cek_absen pada laporan piket.
     */
    public function buatIzinPiket(Request $request, LaporanPiket $laporan)
    {
        $user = $request->user();
        if (! $user || ! $user->can('create_izin_from_piket')) {
            abort(403);
        }

        $validated = $request->validate([
            'entry_index' => 'required|integer|min:0',
            'jenis_izin' => 'required|in:sakit,izin,cuti',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'alasan' => 'required|string|min:10|max:500',
            'bukti_foto' => ['nullable', 'string', 'max:'.PresensiMessages::MAX_FOTO_BASE64, 'regex:/^data:image\/\w+;base64,/'],
            'dengan_surat_dokter' => ['nullable', 'boolean'],
        ]);

        if ($validated['jenis_izin'] === 'sakit' && blank($validated['bukti_foto'] ?? null)) {
            throw ValidationException::withMessages([
                'bukti_foto' => 'Bukti surat dokter wajib diunggah untuk sakit.',
            ]);
        }

        PiketIzinService::convert($laporan, (int) $validated['entry_index'], $validated, $user);

        return back()->with('message', 'Pengajuan izin dibuat dan menunggu persetujuan.');
    }

    /**
     * Halaman Laporan Piket: semua laporan per periode + daftar petugas
     * piket yang belum melapor (jadwal ada, laporan tidak ada).
     */
    public function laporanPiket(Request $request)
    {
        $user = $request->user();
        if (! $user || (! $user->can('view_presensi') && ! $user->can('view_piket'))) {
            abort(403);
        }

        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'unit_sekolah_id' => 'nullable|exists:unit_sekolah,id',
            'area_id' => 'nullable|exists:piket_area,id',
            'status' => 'nullable|in:semua,draft,submitted,perlu_cek,belum',
            'kualitas' => 'nullable|in:semua,pos_belum,fase_terlewat,perlu_cek',
        ]);

        $end = isset($validated['end_date']) ? Carbon::parse($validated['end_date']) : Carbon::today();
        $start = isset($validated['start_date']) ? Carbon::parse($validated['start_date']) : $end->copy()->subDays(6);
        if ($start->diffInDays($end) > 31) {
            $start = $end->copy()->subDays(31);
        }
        $status = $validated['status'] ?? 'semua';

        // Unit: request untuk superadmin, unit sendiri untuk admin unit.
        $unitId = $validated['unit_sekolah_id'] ?? null;
        if ($user->unit_sekolah_id && ! $user->can('view_all_units')) {
            $unitId = $user->unit_sekolah_id;
        }
        $units = $user->can('view_all_units')
            ? UnitSekolah::orderBy('nama')->get(['id', 'nama', 'singkatan'])
            : UnitSekolah::where('id', $unitId)->get(['id', 'nama', 'singkatan']);

        $areaId = $validated['area_id'] ?? null;
        $kualitas = $validated['kualitas'] ?? 'semua';

        $laporan = LaporanPiket::with([
            'pegawai:id,nama_lengkap',
            'unitSekolah:id,nama',
            'area:id,nama',
            'sesi.petugas.pegawai:id,nama_lengkap',
            'sesi.petugas.area:id,nama',
            'sesi.posLogs.pos',
            'sesi.posLogs.overrideBy:id,name',
            'sesi.rounds.area:id,nama',
            'absenSesi.details',
        ])
            ->whereBetween('tanggal', [$start->toDateString(), $end->toDateString()])
            ->when($unitId, fn ($q) => $q->where('unit_sekolah_id', $unitId))
            ->when($areaId, fn ($q) => $q->where('area_id', $areaId))
            ->orderBy('tanggal', 'desc')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function (LaporanPiket $lap) {
                $petugas = $lap->sesi?->petugas->firstWhere('pegawai_id', $lap->pegawai_id);
                $required = $lap->sesi_id && $lap->unitSekolah
                    ? PiketService::requiredCodes($lap->unitSekolah, $petugas)
                    : LaporanPiket::ITEM_WAJIB;

                $itemDone = count(array_filter($required, fn ($kode) => $lap->isItemDone($kode)));
                $posBelum = $lap->sesi ? PiketService::unservedRequiredPos($lap->sesi) : [];
                $posTotal = $lap->unitSekolah
                    ? PiketPos::where('unit_sekolah_id', $lap->unit_sekolah_id)->where('aktif', true)->where('is_wajib', true)->count()
                    : 0;
                $rounds = $lap->sesi ? PiketService::rounds($lap->sesi) : [];
                $roundSelesai = count(array_filter($rounds, fn ($r) => $r['selesai_at'] !== null));

                $posList = collect();
                if ($lap->sesi) {
                    $posList = PiketPos::where('unit_sekolah_id', $lap->unit_sekolah_id)
                        ->where('aktif', true)
                        ->where(fn ($q) => $q->whereNull('area_id')->when($petugas?->area_id, fn ($qq, $a) => $qq->orWhere('area_id', $a)))
                        ->get()
                        ->map(function (PiketPos $pos) use ($lap) {
                            $log = $lap->sesi->posLogs->firstWhere('pos_id', $pos->id);

                            return [
                                'id' => $pos->id,
                                'nama' => $pos->nama,
                                'is_wajib' => (bool) $pos->is_wajib,
                                'tercatat' => $log !== null,
                                'done_at' => $log?->done_at?->format('H:i'),
                                'nihil' => (bool) $log?->nihil,
                                'jumlah' => $log?->jumlah,
                                'catatan' => $log?->catatan,
                                'sumber' => $log?->sumber,
                                'alasan_override' => $log?->alasan_override,
                                'override_by' => $log?->overrideBy?->name,
                            ];
                        });
                }

                $absen = $lap->absenSesi->map(function ($sesiAbsen) {
                    $counts = $sesiAbsen->details->countBy('status');

                    return [
                        'class_label' => $sesiAbsen->class_label,
                        'counts' => [
                            'hadir' => $counts->get('hadir', 0),
                            'telat' => $counts->get('telat', 0),
                            'izin' => $counts->get('izin', 0),
                            'sakit' => $counts->get('sakit', 0),
                            'alpa' => $counts->get('alpa', 0),
                        ],
                        'total' => $sesiAbsen->details->count(),
                        'non_hadir' => $sesiAbsen->details
                            ->where('status', '!=', 'hadir')
                            ->map(fn ($d) => ['nama' => $d->nama_snapshot, 'nis' => $d->nis, 'status' => $d->status, 'keterangan' => $d->keterangan])
                            ->values(),
                    ];
                })->values();

                return [
                    'id' => $lap->id,
                    'tanggal' => $lap->tanggal->format('Y-m-d'),
                    'pegawai' => $lap->pegawai?->only(['id', 'nama_lengkap']),
                    'unit' => $lap->unitSekolah?->nama,
                    'unit_id' => $lap->unit_sekolah_id,
                    'area' => $lap->area?->nama,
                    'area_id' => $lap->area_id,
                    'peran' => $lap->peran,
                    'status' => $lap->status,
                    'sesi' => $lap->sesi ? [
                        'id' => $lap->sesi->id,
                        'status' => $lap->sesi->status,
                        'jam' => $lap->sesi->jam_mulai.'-'.$lap->sesi->jam_selesai,
                        'petugas' => $lap->sesi->petugas->map(fn ($t) => [
                            'nama' => $t->pegawai?->nama_lengkap,
                            'area' => $t->area?->nama,
                            'peran' => $t->peran,
                        ])->values(),
                    ] : null,
                    'pos' => $posList->values(),
                    'pos_belum' => $posBelum,
                    'rounds' => $rounds,
                    'absen' => $absen,
                    'done' => $itemDone,
                    'total' => count($required),
                    'progress' => [
                        'item_done' => $itemDone,
                        'item_total' => count($required),
                        'pos_done' => max(0, $posTotal - count($posBelum)),
                        'pos_total' => $posTotal,
                        'round_done' => $roundSelesai,
                        'round_total' => count($rounds),
                    ],
                    'block_reason' => $lap->status === LaporanPiket::STATUS_DRAFT ? PiketService::sessionBlockReason($lap) : null,
                    'perlu_cek' => $lap->perluCekCount(),
                    'submitted_at' => $lap->submitted_at?->format('Y-m-d H:i'),
                    'items' => $lap->items ?? [],
                    'legacy' => $lap->sesi_id === null,
                ];
            });

        $izinIds = $laporan->flatMap(function ($lap) {
            return collect($lap['items'][LaporanPiket::ITEM_CEK_ABSEN]['entries'] ?? [])
                ->pluck('pengajuan_izin_id')
                ->filter();
        })->unique()->values();

        $izinMap = $izinIds->isEmpty()
            ? collect()
            : PengajuanIzin::whereIn('id', $izinIds)->get(['id', 'status', 'jenis_izin'])->keyBy('id');

        if ($izinMap->isNotEmpty()) {
            $laporan = $laporan->map(function ($lap) use ($izinMap) {
                $entries = $lap['items'][LaporanPiket::ITEM_CEK_ABSEN]['entries'] ?? [];

                foreach ($entries as $index => $entry) {
                    $izin = $izinMap->get($entry['pengajuan_izin_id'] ?? null);
                    if ($izin) {
                        $entries[$index]['pengajuan_izin'] = $izin->only(['id', 'status', 'jenis_izin']);
                    }
                }

                $lap['items'][LaporanPiket::ITEM_CEK_ABSEN]['entries'] = $entries;

                return $lap;
            });
        }

        if ($status === 'draft' || $status === 'submitted') {
            $laporan = $laporan->where('status', $status)->values();
        } elseif ($status === 'perlu_cek') {
            $laporan = $laporan->where('perlu_cek', '>', 0)->values();
        }

        if ($kualitas === 'pos_belum') {
            $laporan = $laporan->filter(fn ($lap) => $lap['pos_belum'] !== [])->values();
        } elseif ($kualitas === 'fase_terlewat') {
            $laporan = $laporan->filter(fn ($lap) => collect($lap['rounds'])->contains(fn ($r) => $r['selesai_at'] === null))->values();
        } elseif ($kualitas === 'perlu_cek') {
            $laporan = $laporan->filter(fn ($lap) => $lap['perlu_cek'] > 0)->values();
        }

        // Belum melapor: jadwal piket per tanggal minus yang sudah ada laporan.
        $belum = collect();
        if ($status === 'semua' || $status === 'belum') {
            $hariMap = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
            $sudah = LaporanPiket::whereBetween('tanggal', [$start->toDateString(), $end->toDateString()])
                ->when($unitId, fn ($q) => $q->where('unit_sekolah_id', $unitId))
                ->get(['pegawai_id', 'tanggal'])
                ->map(fn ($r) => $r->pegawai_id.'|'.$r->tanggal->format('Y-m-d'))
                ->all();
            $sudah = array_flip($sudah);

            $cursor = $start->copy();
            while ($cursor->lte($end)) {
                $jadwals = Jadwal::with(['pegawai:id,nama_lengkap', 'unitSekolah:id,nama'])
                    ->where('hari', $hariMap[$cursor->format('l')])
                    ->where('jenis_jadwal', 'piket')
                    ->when($unitId, fn ($q) => $q->where('unit_sekolah_id', $unitId))
                    ->orderBy('jam_mulai')
                    ->get();
                foreach ($jadwals as $j) {
                    if (! isset($sudah[$j->pegawai_id.'|'.$cursor->toDateString()])) {
                        $belum->push([
                            'tanggal' => $cursor->toDateString(),
                            'pegawai' => $j->pegawai?->only(['id', 'nama_lengkap']),
                            'unit' => $j->unitSekolah?->nama,
                            'jam' => substr((string) $j->jam_mulai, 0, 5).'-'.substr((string) $j->jam_selesai, 0, 5),
                        ]);
                    }
                }
                $cursor->addDay();
            }
        }

        if ($status === 'belum') {
            $laporan = collect();
        }

        $areas = PiketArea::query()
            ->when($unitId, fn ($q) => $q->where('unit_sekolah_id', $unitId))
            ->orderBy('urutan')
            ->get(['id', 'nama', 'unit_sekolah_id']);

        return Inertia::render('Laporan/Piket', [
            'filters' => [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'unit_sekolah_id' => $unitId,
                'status' => $status,
                'area_id' => $areaId,
                'kualitas' => $kualitas,
            ],
            'units' => $units,
            'areas' => $areas,
            'laporan' => $laporan->values(),
            'belum' => $belum->values(),
            'label' => LaporanPiket::ITEM_LABEL,
        ]);
    }
}
