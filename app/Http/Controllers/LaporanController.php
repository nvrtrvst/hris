<?php

namespace App\Http\Controllers;

use App\Exports\LaporanLemburanExport;
use App\Exports\LaporanPenggajianExport;
use App\Exports\LaporanPresensiExport;
use App\Http\Requests\KcdReportRequest;
use App\Http\Requests\LaporanGenerateRequest;
use App\Models\LaporanKcdCetak;
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
        $export = null;
        if ($validated['type'] === 'presensi') {
            $export = new LaporanPresensiExport($validated['start_date'], $validated['end_date'], $validated['unit_sekolah_id'] ?? null, $jenis);
        } elseif ($validated['type'] === 'penggajian') {
            $export = new LaporanPenggajianExport($validated['start_date'], $validated['end_date'], $validated['unit_sekolah_id'] ?? null, $jenis);
        } elseif ($validated['type'] === 'lemburan') {
            $export = new LaporanLemburanExport($validated['start_date'], $validated['end_date'], $validated['unit_sekolah_id'] ?? null, $jenis);
        }

        if (! $export) {
            return response()->json(['error' => 'Invalid type'], 400);
        }

        $data = $export->collection()->take(500);
        $headings = $export->headings();

        $mappedData = $data->map(function ($item) use ($export) {
            return $export->map($item);
        });

        return response()->json([
            'headings' => $headings,
            'data' => $mappedData,
        ]);
    }

    public function exportPresensi(LaporanGenerateRequest $request)
    {
        $validated = $request->validated();

        return Excel::download(
            new LaporanPresensiExport($validated['start_date'], $validated['end_date'], $validated['unit_sekolah_id'] ?? null, $validated['jenis_filter'] ?? null),
            'Laporan_Presensi_'.$validated['start_date'].'_to_'.$validated['end_date'].'.xlsx'
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
     * Resolve logo yayasan ke path file lokal untuk DOMPDF.
     */
    protected function resolveYayasanLogoPath(): ?string
    {
        $rel = config('kcd.yayasan_logo');
        if (! $rel) {
            return null;
        }

        $disk = config('filesystems.image_disk', 'public');
        $root = config("filesystems.disks.$disk.root");
        $path = rtrim($root, '/').'/'.ltrim($rel, '/');

        return file_exists($path) ? $path : null;
    }
}
