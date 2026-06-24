<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\UnitSekolah;
use App\Exports\LaporanPresensiExport;
use App\Exports\LaporanPenggajianExport;
use App\Exports\LaporanLemburanExport;
use Maatwebsite\Excel\Facades\Excel;

class LaporanController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $isAdmin = $user && in_array($user->role, ['superadmin', 'admin_unit']);
        if (!$isAdmin) {
            abort(403, 'Akses Ditolak. Anda tidak memiliki izin melihat modul laporan.');
        }

        if ($user->role === 'admin_unit') {
            $units = UnitSekolah::where('id', $user->unit_sekolah_id)->get();
        } else {
            $units = UnitSekolah::all();
        }

        return Inertia::render('Laporan/Index', [
            'units' => $units
        ]);
    }

    public function preview(Request $request)
    {
        $user = $request->user();
        if ($user->role === 'admin_unit') {
            $request->merge(['unit_sekolah_id' => $user->unit_sekolah_id]);
        }

        $request->validate([
            'type' => 'required|in:presensi,penggajian,lemburan',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'unit_sekolah_id' => 'nullable|exists:unit_sekolah,id'
        ]);

        $export = null;
        if ($request->type === 'presensi') {
            $export = new LaporanPresensiExport($request->start_date, $request->end_date, $request->unit_sekolah_id);
        } elseif ($request->type === 'penggajian') {
            $export = new LaporanPenggajianExport($request->start_date, $request->end_date, $request->unit_sekolah_id);
        } elseif ($request->type === 'lemburan') {
            $export = new LaporanLemburanExport($request->start_date, $request->end_date, $request->unit_sekolah_id);
        }

        if (!$export) {
            return response()->json(['error' => 'Invalid type'], 400);
        }

        $data = $export->collection();
        $headings = $export->headings();

        $mappedData = $data->map(function($item) use ($export) {
            return $export->map($item);
        });

        return response()->json([
            'headings' => $headings,
            'data' => $mappedData
        ]);
    }

    public function exportPresensi(Request $request)
    {
        $user = $request->user();
        if ($user->role === 'admin_unit') {
            $request->merge(['unit_sekolah_id' => $user->unit_sekolah_id]);
        }
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'unit_sekolah_id' => 'nullable|exists:unit_sekolah,id'
        ]);

        return Excel::download(
            new LaporanPresensiExport($request->start_date, $request->end_date, $request->unit_sekolah_id), 
            'Laporan_Presensi_'.$request->start_date.'_to_'.$request->end_date.'.xlsx'
        );
    }

    public function exportPenggajian(Request $request)
    {
        $user = $request->user();
        if ($user->role === 'admin_unit') {
            $request->merge(['unit_sekolah_id' => $user->unit_sekolah_id]);
        }
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'unit_sekolah_id' => 'nullable|exists:unit_sekolah,id'
        ]);

        return Excel::download(
            new LaporanPenggajianExport($request->start_date, $request->end_date, $request->unit_sekolah_id), 
            'Laporan_Rekap_Gaji_'.$request->start_date.'_to_'.$request->end_date.'.xlsx'
        );
    }

    public function exportLemburan(Request $request)
    {
        $user = $request->user();
        if ($user->role === 'admin_unit') {
            $request->merge(['unit_sekolah_id' => $user->unit_sekolah_id]);
        }
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'unit_sekolah_id' => 'nullable|exists:unit_sekolah,id'
        ]);

        return Excel::download(
            new LaporanLemburanExport($request->start_date, $request->end_date, $request->unit_sekolah_id), 
            'Laporan_Lemburan_Potongan_'.$request->start_date.'_to_'.$request->end_date.'.xlsx'
        );
    }
}
