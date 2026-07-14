<?php

namespace App\Http\Controllers;

use App\Models\UnitSekolah;
use Illuminate\Http\Request;

class UnitSekolahController extends Controller
{
    public function index()
    {
        $units = UnitSekolah::all();

        return inertia('UnitSekolah/Index', ['units' => $units]);
    }

    public function create()
    {
        return inertia('UnitSekolah/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'singkatan' => 'required|string|max:255',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'radius_meter' => 'required|integer|min:10',
        ]);

        UnitSekolah::create($validated);

        return redirect()->route('unit-sekolah.index')->with('message', 'Unit Sekolah berhasil ditambahkan.');
    }

    public function edit(UnitSekolah $unit_sekolah)
    {
        return inertia('UnitSekolah/Edit', ['unit' => $unit_sekolah]);
    }

    public function update(Request $request, UnitSekolah $unit_sekolah)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'singkatan' => 'required|string|max:255',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'radius_meter' => 'required|integer|min:10',
        ]);

        $unit_sekolah->update($validated);

        return redirect()->route('unit-sekolah.index')->with('message', 'Unit Sekolah berhasil diperbarui.');
    }
}
