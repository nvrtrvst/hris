<?php

namespace App\Http\Controllers;

use App\Models\KomponenGaji;
use Illuminate\Http\Request;

class KomponenGajiController extends Controller
{
    public function index()
    {
        $komponens = KomponenGaji::all();
        return inertia('Payroll/Komponen', ['komponens' => $komponens]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string',
            'tipe' => 'required|in:pendapatan,potongan',
            'jenis' => 'required|in:fixed,persentase,dinamis_kehadiran',
            'nilai_default' => 'nullable|numeric',
            'is_taxable' => 'boolean',
            'is_active' => 'boolean',
        ]);
        
        $validated['is_taxable'] = $request->has('is_taxable') ? $request->is_taxable : true;
        $validated['is_active'] = $request->has('is_active') ? $request->is_active : true;

        KomponenGaji::create($validated);
        return redirect()->route('komponen-gaji.index')->with('message', 'Komponen Gaji berhasil ditambah.');
    }

    public function update(Request $request, $id)
    {
        $komponen = KomponenGaji::findOrFail($id);
        $validated = $request->validate([
            'nama' => 'required|string',
            'tipe' => 'required|in:pendapatan,potongan',
            'jenis' => 'required|in:fixed,persentase,dinamis_kehadiran',
            'nilai_default' => 'nullable|numeric',
            'is_taxable' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $komponen->update($validated);
        return redirect()->route('komponen-gaji.index')->with('message', 'Komponen Gaji berhasil diupdate.');
    }

    public function destroy($id)
    {
        KomponenGaji::destroy($id);
        return redirect()->route('komponen-gaji.index')->with('message', 'Komponen Gaji dihapus.');
    }
}
