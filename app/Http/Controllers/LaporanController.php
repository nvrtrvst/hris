<?php

namespace App\Http\Controllers;

use App\Exports\LaporanLemburanExport;
use App\Exports\LaporanPenggajianExport;
use App\Exports\LaporanPresensiExport;
use App\Http\Requests\LaporanGenerateRequest;
use App\Models\UnitSekolah;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class LaporanController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $isAdmin = $user && $user->can('view_dashboard');
        if (! $isAdmin) {
            abort(403, 'Akses Ditolak. Anda tidak memiliki izin melihat modul laporan.');
        }

        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
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

        $export = null;
        if ($validated['type'] === 'presensi') {
            $export = new LaporanPresensiExport($validated['start_date'], $validated['end_date'], $validated['unit_sekolah_id']);
        } elseif ($validated['type'] === 'penggajian') {
            $export = new LaporanPenggajianExport($validated['start_date'], $validated['end_date'], $validated['unit_sekolah_id']);
        } elseif ($validated['type'] === 'lemburan') {
            $export = new LaporanLemburanExport($validated['start_date'], $validated['end_date'], $validated['unit_sekolah_id']);
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
            new LaporanPresensiExport($validated['start_date'], $validated['end_date'], $validated['unit_sekolah_id'] ?? null),
            'Laporan_Presensi_'.$validated['start_date'].'_to_'.$validated['end_date'].'.xlsx'
        );
    }

    public function exportPenggajian(LaporanGenerateRequest $request)
    {
        $validated = $request->validated();

        return Excel::download(
            new LaporanPenggajianExport($validated['start_date'], $validated['end_date'], $validated['unit_sekolah_id'] ?? null),
            'Laporan_Rekap_Gaji_'.$validated['start_date'].'_to_'.$validated['end_date'].'.xlsx'
        );
    }

    public function exportLemburan(LaporanGenerateRequest $request)
    {
        $validated = $request->validated();

        return Excel::download(
            new LaporanLemburanExport($validated['start_date'], $validated['end_date'], $validated['unit_sekolah_id'] ?? null),
            'Laporan_Lemburan_Potongan_'.$validated['start_date'].'_to_'.$validated['end_date'].'.xlsx'
        );
    }
}
