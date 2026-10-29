<?php

namespace App\Http\Controllers;

use App\Exceptions\PdfTooLargeException;
use App\Exports\LaporanKeterlambatanSiswaExport;
use App\Services\KeterlambatanSiswaReportService;
use App\Services\LaporanPdfService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class LaporanKeterlambatanSiswaController extends Controller
{
    public function index(Request $request, KeterlambatanSiswaReportService $service)
    {
        $user = $request->user();
        abort_unless(
            $user && ($user->can('view_piket') || $user->can('view_presensi') || $user->can('view_absen_siswa')),
            403,
        );

        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'unit_id' => 'nullable|integer|exists:unit_sekolah,id',
            'class_label' => 'nullable|string|max:100',
            'search' => 'nullable|string|max:100',
        ]);

        $report = $service->report($user, $validated);

        return Inertia::render('Laporan/KeterlambatanSiswa', $report);
    }

    public function exportExcel(Request $request, KeterlambatanSiswaReportService $service)
    {
        $user = $request->user();
        abort_unless(
            $user && ($user->can('view_piket') || $user->can('view_presensi') || $user->can('view_absen_siswa')),
            403,
        );

        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'unit_id' => 'nullable|integer|exists:unit_sekolah,id',
            'class_label' => 'nullable|string|max:100',
            'search' => 'nullable|string|max:100',
        ]);

        $report = $service->report($user, $validated);
        [$headings, $rows] = $service->exportRows($report);

        return Excel::download(
            new LaporanKeterlambatanSiswaExport($rows, $headings),
            'Laporan_Keterlambatan_Siswa_'.$report['filters']['start_date'].'_to_'.$report['filters']['end_date'].'.xlsx',
        );
    }

    public function exportPdf(Request $request, KeterlambatanSiswaReportService $service)
    {
        $user = $request->user();
        abort_unless(
            $user && ($user->can('view_piket') || $user->can('view_presensi') || $user->can('view_absen_siswa')),
            403,
        );

        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'unit_id' => 'nullable|integer|exists:unit_sekolah,id',
            'class_label' => 'nullable|string|max:100',
            'search' => 'nullable|string|max:100',
        ]);

        $report = $service->report($user, $validated);
        [$headings, $rows] = $service->exportRows($report);

        try {
            $result = app(LaporanPdfService::class)->generateKeterlambatanSiswa(
                $report['filters'],
                $report['filters']['unit_id'] ?? null,
                $headings,
                $rows,
            );

            return response($result['content'], 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$result['filename'].'"',
                'Content-Length' => strlen($result['content']),
            ]);
        } catch (PdfTooLargeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
