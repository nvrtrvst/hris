<?php

namespace App\Http\Controllers;

use App\Exports\LaporanLemburanExport;
use App\Exports\LaporanPenggajianExport;
use App\Exports\LaporanPresensiExport;
use App\Exports\LaporanRekapKehadiranExport;
use App\Exports\LaporanRekapMengajarExport;
use App\Http\Requests\KcdReportRequest;
use App\Http\Requests\LaporanGenerateRequest;
use App\Models\LaporanKcdCetak;
use App\Models\Pegawai;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Services\KcdReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
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

        $jenis = $validated['jenis_filter'] ?? null;
        $tipe = $validated['tipe_filter'] ?? null;
        $export = null;
        if ($validated['type'] === 'presensi') {
            $export = new LaporanPresensiExport($validated['start_date'], $validated['end_date'], $validated['unit_sekolah_id'] ?? null, $jenis, $tipe);
        } elseif ($validated['type'] === 'penggajian') {
            $export = new LaporanPenggajianExport($validated['start_date'], $validated['end_date'], $validated['unit_sekolah_id'] ?? null, $jenis);
        } elseif ($validated['type'] === 'lemburan') {
            $export = new LaporanLemburanExport($validated['start_date'], $validated['end_date'], $validated['unit_sekolah_id'] ?? null, $jenis);
        } elseif ($validated['type'] === 'rekap_mengajar') {
            $export = new LaporanRekapMengajarExport($validated['start_date'], $validated['end_date'], $validated['unit_sekolah_id'] ?? null, $jenis);
        } elseif ($validated['type'] === 'rekap_kehadiran') {
            $export = new LaporanRekapKehadiranExport($validated['start_date'], $validated['end_date'], $validated['unit_sekolah_id'] ?? null, $jenis);
        }

        if (! $export) {
            return response()->json(['error' => 'Invalid type'], 400);
        }

        $data = $export->collection()->take(500);
        $headings = $export->headings();

        $mappedData = $data->map(function ($item) use ($export) {
            return $export->map($item);
        });

        $payload = [
            'headings' => $headings,
            'data' => $mappedData,
        ];

        // Khusus rekap mengajar: sertakan data kalender (guru × tanggal)
        // untuk tampilan grid mingguan di frontend.
        if ($validated['type'] === 'rekap_mengajar') {
            $payload['calendar'] = $export->calendarData($data);
            $payload['summary'] = $export->summaryData();
            $payload['pegawai_ids'] = $data->pluck('pegawai.id')->values();
        }

        // Khusus rekap kehadiran: sertakan summary stats + pegawai IDs untuk drill-down.
        if ($validated['type'] === 'rekap_kehadiran') {
            $payload['summary'] = $export->summaryData();
            $payload['pegawai_ids'] = $data->pluck('pegawai.id')->values();
        }

        return response()->json($payload);
    }

    public function exportPresensi(LaporanGenerateRequest $request)
    {
        $validated = $request->validated();

        return Excel::download(
            new LaporanPresensiExport($validated['start_date'], $validated['end_date'], $validated['unit_sekolah_id'] ?? null, $validated['jenis_filter'] ?? null, $validated['tipe_filter'] ?? null),
            'Laporan_Presensi_'.$validated['start_date'].'_to_'.$validated['end_date'].'.xlsx'
        );
    }

    public function exportRekapMengajar(LaporanGenerateRequest $request)
    {
        $validated = $request->validated();

        return Excel::download(
            new LaporanRekapMengajarExport($validated['start_date'], $validated['end_date'], $validated['unit_sekolah_id'] ?? null, $validated['jenis_filter'] ?? null),
            'Laporan_Rekap_Mengajar_'.$validated['start_date'].'_to_'.$validated['end_date'].'.xlsx'
        );
    }

    public function exportRekapKehadiran(LaporanGenerateRequest $request)
    {
        $validated = $request->validated();

        return Excel::download(
            new LaporanRekapKehadiranExport($validated['start_date'], $validated['end_date'], $validated['unit_sekolah_id'] ?? null, $validated['jenis_filter'] ?? null),
            'Laporan_Rekap_Kehadiran_'.$validated['start_date'].'_to_'.$validated['end_date'].'.xlsx'
        );
    }

    public function exportPenggajian(LaporanGenerateRequest $request)
    {
        $validated = $request->validated();

        return Excel::download(
            new LaporanPenggajianExport($validated['start_date'], $validated['end_date'], $validated['unit_sekolah_id'] ?? null, $validated['jenis_filter'] ?? null),
            'Laporan_Rekap_Gaji_'.$validated['start_date'].'_to_'.$validated['end_date'].'.xlsx'
        );
    }

    public function exportLemburan(LaporanGenerateRequest $request)
    {
        $validated = $request->validated();

        return Excel::download(
            new LaporanLemburanExport($validated['start_date'], $validated['end_date'], $validated['unit_sekolah_id'] ?? null, $validated['jenis_filter'] ?? null),
            'Laporan_Lemburan_Potongan_'.$validated['start_date'].'_to_'.$validated['end_date'].'.xlsx'
        );
    }

    public function exportPdf(LaporanGenerateRequest $request)
    {
        $validated = $request->validated();
        $type = $validated['type'];

        set_time_limit(300);

        $export = match ($type) {
            'presensi' => new LaporanPresensiExport($validated['start_date'], $validated['end_date'], $validated['unit_sekolah_id'] ?? null, $validated['jenis_filter'] ?? null, $validated['tipe_filter'] ?? null),
            'penggajian' => new LaporanPenggajianExport($validated['start_date'], $validated['end_date'], $validated['unit_sekolah_id'] ?? null, $validated['jenis_filter'] ?? null),
            'lemburan' => new LaporanLemburanExport($validated['start_date'], $validated['end_date'], $validated['unit_sekolah_id'] ?? null, $validated['jenis_filter'] ?? null),
            'rekap_mengajar' => new LaporanRekapMengajarExport($validated['start_date'], $validated['end_date'], $validated['unit_sekolah_id'] ?? null, $validated['jenis_filter'] ?? null),
            'rekap_kehadiran' => new LaporanRekapKehadiranExport($validated['start_date'], $validated['end_date'], $validated['unit_sekolah_id'] ?? null, $validated['jenis_filter'] ?? null),
        };

        $rows = $export->collection()->map(fn ($item) => $export->map($item))->all();
        $headings = $export->headings();

        if (count($rows) > 5000) {
            return response()->json([
                'message' => 'Data terlalu banyak untuk PDF ('.count($rows).' baris). Gunakan filter yang lebih spesifik atau export ke Excel.',
            ], 422);
        }

        // Kop surat: unit terpilih → data unit itu; semua unit → unit
        // induk "Yayasan"; fallback terakhir → config env. Data (alamat,
        // telepon, web, logo) dikelola lewat menu Unit Sekolah.
        $kopUnit = null;
        if (! empty($validated['unit_sekolah_id'])) {
            $kopUnit = UnitSekolah::find($validated['unit_sekolah_id']);
            $unitName = $kopUnit?->nama ?? 'Semua Unit Sekolah';
        } else {
            $unitName = 'Semua Unit Sekolah';
            $kopUnit = UnitSekolah::where('nama', 'like', 'Yayasan%')->first();
        }

        $periodeStr = Carbon::parse($validated['start_date'])->format('d/m/Y')
            .' s/d '.Carbon::parse($validated['end_date'])->format('d/m/Y');

        $logoPath = $this->resolveUnitLogoPath($kopUnit) ?? $this->resolveYayasanLogoPath();
        $logoWidth = null;
        if ($logoPath && file_exists($logoPath)) {
            $sz = @getimagesize($logoPath);
            if ($sz) {
                $logoWidth = (int) round(64 * $sz[0] / $sz[1]);
            }
        }

        $kop = [
            'name' => $kopUnit?->nama ? strtoupper($kopUnit->nama) : config('yayasan.name'),
            'tagline' => config('yayasan.tagline'),
            'address' => $kopUnit?->alamat ?: config('yayasan.address'),
            'phone' => $kopUnit?->telepon ?: config('yayasan.phone'),
            'email' => config('yayasan.email'),
            'website' => $kopUnit?->web ?: config('yayasan.website'),
        ];

        $title = match ($type) {
            'presensi' => 'LAPORAN REKAPITULASI PRESENSI PEGAWAI',
            'penggajian' => 'LAPORAN REKAPITULASI PENGGAJIAN PEGAWAI',
            'lemburan' => 'LAPORAN LEMBUR PEGAWAI',
            'rekap_mengajar' => 'LAPORAN REKAPITULASI PRESENSI MENGAJAR',
            'rekap_kehadiran' => 'LAPORAN REKAPITULASI KEHADIRAN',
        };

        $filename = match ($type) {
            'presensi' => 'Laporan_Presensi',
            'penggajian' => 'Laporan_Rekap_Gaji',
            'lemburan' => 'Laporan_Lemburan',
            'rekap_mengajar' => 'Laporan_Rekap_Mengajar',
            'rekap_kehadiran' => 'Laporan_Rekap_Kehadiran',
        };

        try {
            $pdf = Pdf::loadView('exports.pdf-laporan', compact('headings', 'rows', 'title', 'periodeStr', 'unitName', 'logoPath', 'logoWidth', 'kop'))
                ->setPaper('A4', 'landscape');

            return $pdf->download($filename.'_'.$validated['start_date'].'_to_'.$validated['end_date'].'.pdf');
        } catch (\Throwable $e) {
            \Log::error('PDF laporan gagal', [
                'type' => $type,
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
            ->orderBy('tanggal')
            ->get();

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
            'alpa' => $rows->where('status', 'alpa')->count(),
        ];

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

        $rows = Presensi::with(['jadwal.mataPelajaran', 'unitSekolah'])
            ->where('pegawai_id', $pegawai->id)
            ->whereBetween('tanggal', [$request->start_date, $request->end_date])
            ->whereNotNull('jadwal_id')
            ->where('tipe_presensi', 'mengajar')
            ->orderBy('tanggal')
            ->orderByRaw('COALESCE(jam_masuk, "23:59") asc')
            ->get();

        $detail = $rows->map(fn ($p) => [
            'tanggal' => $p->tanggal->format('Y-m-d'),
            'hari' => $p->tanggal->locale('id')->translatedFormat('l'),
            'mapel' => $p->jadwal?->mataPelajaran?->nama ?? '-',
            'kelas' => $p->jadwal?->kelas_label ?? '-',
            'jam_mulai' => $p->jam_masuk,
            'jam_selesai' => $p->jam_keluar,
            'status' => $p->status,
            'unit' => $p->unitSekolah?->nama ?? '-',
        ]);

        $summary = [
            'terjadwal' => $rows->count(),
            'hadir' => $rows->where('status', 'hadir')->count(),
            'telat' => $rows->where('status', 'telat')->count(),
            'alpa' => $rows->where('status', 'alpa')->count(),
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
        if (! $unit?->logo) {
            return null;
        }

        $disk = config('filesystems.image_disk', 'public');
        $root = config("filesystems.disks.$disk.root");
        $path = rtrim($root, '/').'/'.ltrim($unit->logo, '/');

        return file_exists($path) ? $path : null;
    }

    /**
     * Resolve logo yayasan ke path file lokal untuk DOMPDF.
     */
    protected function resolveYayasanLogoPath(): ?string
    {
        $rel = config('kcd.yayasan_logo');
        if (! $rel) {
            return null;
        }

        $public = public_path($rel);
        if (file_exists($public)) {
            return $public;
        }

        $disk = config('filesystems.image_disk', 'public');
        $root = config("filesystems.disks.$disk.root");
        $path = rtrim($root, '/').'/'.ltrim($rel, '/');

        return file_exists($path) ? $path : null;
    }
}
