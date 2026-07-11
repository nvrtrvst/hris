<?php

namespace App\Http\Controllers;

use App\Models\SkalaMasaBakti;
use Illuminate\Http\Request;

class SkalaMasaBaktiController extends Controller
{
    public function index()
    {
        $skalas = SkalaMasaBakti::orderBy('masa_kerja_tahun', 'asc')->get();

        return inertia('Payroll/SkalaMasaBakti', ['skalas' => $skalas]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'masa_kerja_tahun' => 'required|integer|min:0|unique:skala_masa_baktis,masa_kerja_tahun',
            'nominal_gaji' => 'required|numeric|min:0',
        ]);

        SkalaMasaBakti::create($validated);

        return redirect()->back()->with('message', 'Skala masa bakti berhasil ditambah.');
    }

    public function destroy($id)
    {
        SkalaMasaBakti::destroy($id);

        return redirect()->back()->with('message', 'Skala masa bakti dihapus.');
    }
}
