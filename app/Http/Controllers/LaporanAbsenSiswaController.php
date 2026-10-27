<?php

namespace App\Http\Controllers;

use App\Exceptions\PdfTooLargeException;
use App\Exports\LaporanAbsenSiswaExport;
use App\Http\Requests\AbsenSiswaReportRequest;
use App\Models\PegawaiKelasBinaan;
use App\Models\UnitSekolah;
use App\Services\AbsenSiswaReportService;
use App\Services\LaporanPdfService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class LaporanAbsenSiswaController extends Controller
{
    public function index(AbsenSiswaReportRequest $request, AbsenSiswaReportService $service)
    {
        $report = $service->report($request->user(), $request->validated());

        return Inertia::render('Laporan/AbsenSiswa', [
            'filters' => $report['filters'],
            'summary' => $report['summary'],
            'sessions' => collect($report['sessions'])->values(),
            'students' => $report['students'],
            'classes' => $report['classes'],
            'classSummary' => collect($report['classSummary'])->values(),
            'perhatian' => $report['perhatian'],
            'units' => $this->unitsFor($request->user()),
            'canFilterUnit' => $request->user()->can('view_all_units'),
        ]);
    }

    public function detail(Request $request, AbsenSiswaReportService $service)
    {
        abort_unless($request->user()?->can('view_absen_siswa'), 403);

        $validated = $request->validate([
            'nis' => 'required|string|max:30',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'unit_id' => 'nullable|integer|exists:unit_sekolah,id',
            'class_label' => 'nullable|string|max:100',
        ]);

        return response()->json($service->detail($request->user(), $validated));
    }

    public function exportExcel(AbsenSiswaReportRequest $request, AbsenSiswaReportService $service)
    {
        $report = $service->report($request->user(), $request->validated());
        [$headings, $rows] = $this->exportRows($report);

        return Excel::download(
            new LaporanAbsenSiswaExport($rows, $headings),
            'Laporan_Absen_Siswa_'.$report['filters']['start_date'].'_to_'.$report['filters']['end_date'].'.xlsx',
        );
    }

    public function exportPdf(AbsenSiswaReportRequest $request, AbsenSiswaReportService $service)
    {
        $report = $service->report($request->user(), $request->validated());
        [$headings, $rows] = $this->exportRows($report);

        try {
            $result = app(LaporanPdfService::class)->generateAbsenSiswa(
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

    private function exportRows(array $report): array
    {
        if ($report['mode'] === 'daily') {
            $rows = $report['sessions']->flatMap(fn ($session) => $session['non_hadir']->map(fn ($row) => [
                'kelas' => $session['class_label'],
                'unit' => $session['unit'],
                'nis' => $row['nis'],
                'nama' => $row['nama'],
                'status' => $row['status'],
                'keterangan' => $row['keterangan'] ?? '',
                'petugas' => $session['pegawai'],
                'jam' => $session['done_at'],
            ]))->values()->all();

            return [
                ['Kelas', 'Unit', 'NIS', 'Nama', 'Status', 'Keterangan', 'Petugas', 'Jam'],
                $rows,
            ];
        }

        return [
            ['Unit', 'Kelas', 'NIS', 'Nama', 'Sesi', 'Hadir', 'Telat', 'Izin', 'Sakit', 'Alpa', '% Hadir'],
            $report['students']->map(fn ($student) => [
                'unit' => $student['unit'],
                'kelas' => $student['class_label'],
                'nis' => $student['nis'],
                'nama' => $student['nama'],
                'sesi' => $student['sesi_tercatat'],
                'hadir' => $student['hadir'],
                'telat' => $student['telat'],
                'izin' => $student['izin'],
                'sakit' => $student['sakit'],
                'alpa' => $student['alpa'],
                'persen' => $student['persen'].'%',
            ])->all(),
        ];
    }

    private function unitsFor($user)
    {
        if ($user->can('view_all_units')) {
            return UnitSekolah::orderBy('nama')->get(['id', 'nama']);
        }

        if ($user->hasRole('wali_kelas') && ! $user->can('view_presensi')) {
            $ids = PegawaiKelasBinaan::aktif()
                ->where('pegawai_id', $user->pegawai?->id)
                ->pluck('unit_sekolah_id')
                ->unique()
                ->all();

            return UnitSekolah::whereIn('id', $ids)->orderBy('nama')->get(['id', 'nama']);
        }

        return UnitSekolah::whereKey($user->unit_sekolah_id)->get(['id', 'nama']);
    }
}
