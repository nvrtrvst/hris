<?php

namespace App\Http\Controllers;

use App\Models\KomponenGaji;
use Illuminate\Http\Request;

class KomponenGajiController extends Controller
{
    public function index()
    {
        $komponens = KomponenGaji::orderBy('urutan', 'asc')->get();
        return inertia('Payroll/Komponen', ['komponens' => $komponens]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string',
            'tipe' => 'required|in:pendapatan,potongan',
            'jenis' => 'required|in:fixed,persentase,dinamis_kehadiran,dinamis_jam_mengajar,dinamis_masa_bakti',
            'nilai_default' => 'nullable|numeric',
            'is_taxable' => 'boolean',
            'is_active' => 'boolean',
            'urutan' => 'nullable|integer',
            'tampil_di_matrix' => 'boolean',
        ]);
        
        $validated['is_taxable'] = $request->has('is_taxable') ? $request->is_taxable : true;
        $validated['is_active'] = $request->has('is_active') ? $request->is_active : true;
        $validated['tampil_di_matrix'] = $request->has('tampil_di_matrix') ? $request->tampil_di_matrix : true;
        if (!isset($validated['urutan'])) {
            $validated['urutan'] = 99;
        }

        KomponenGaji::create($validated);
        return redirect()->route('komponen-gaji.index')->with('message', 'Komponen Gaji berhasil ditambah.');
    }

    public function update(Request $request, $id)
    {
        $komponen = KomponenGaji::findOrFail($id);
        $validated = $request->validate([
            'nama' => 'required|string',
            'tipe' => 'required|in:pendapatan,potongan',
            'jenis' => 'required|in:fixed,persentase,dinamis_kehadiran,dinamis_jam_mengajar,dinamis_masa_bakti',
            'nilai_default' => 'nullable|numeric',
            'is_taxable' => 'boolean',
            'is_active' => 'boolean',
            'urutan' => 'nullable|integer',
            'tampil_di_matrix' => 'boolean',
        ]);

        if (!isset($validated['tampil_di_matrix'])) {
            $validated['tampil_di_matrix'] = false;
        }
        if (!isset($validated['urutan'])) {
            $validated['urutan'] = 99;
        }

        $komponen->update($validated);
        return redirect()->route('komponen-gaji.index')->with('message', 'Komponen Gaji berhasil diupdate.');
    }

    public function destroy($id)
    {
        KomponenGaji::destroy($id);
        return redirect()->route('komponen-gaji.index')->with('message', 'Komponen Gaji dihapus.');
    }
}
