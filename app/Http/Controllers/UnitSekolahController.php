<?php

namespace App\Http\Controllers;

use App\Models\UnitSekolah;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
            'logo' => 'nullable|image|mimes:jpeg,png,webp|max:1024',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'radius_meter' => 'required|integer|min:10|max:100000',
            'jam_masuk_kantor' => 'required|date_format:H:i',
            'jam_pulang_kantor' => 'nullable|date_format:H:i',
        ]);

        $disk = config('filesystems.image_disk', 'public');
        $newLogo = $request->hasFile('logo') ? $request->file('logo')->store('unit_logos', $disk) : null;
        if ($newLogo) {
            $validated['logo'] = $newLogo;
        }

        try {
            UnitSekolah::create($validated);
        } catch (\Throwable $exception) {
            if ($newLogo) {
                Storage::disk($disk)->delete($newLogo);
            }
            throw $exception;
        }

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
            'logo' => 'nullable|image|mimes:jpeg,png,webp|max:1024',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'radius_meter' => 'required|integer|min:10|max:100000',
            'jam_masuk_kantor' => 'required|date_format:H:i',
            'jam_pulang_kantor' => 'nullable|date_format:H:i',
        ]);

        $disk = config('filesystems.image_disk', 'public');
        $oldLogo = $unit_sekolah->logo;
        $newLogo = $request->hasFile('logo') ? $request->file('logo')->store('unit_logos', $disk) : null;
        if ($newLogo) {
            $validated['logo'] = $newLogo;
        } else {
            unset($validated['logo']);
        }

        try {
            $unit_sekolah->update($validated);
        } catch (\Throwable $exception) {
            if ($newLogo) {
                Storage::disk($disk)->delete($newLogo);
            }
            throw $exception;
        }

        if ($newLogo && $oldLogo) {
            Storage::disk($disk)->delete($oldLogo);
        }

        return redirect()->route('unit-sekolah.index')->with('message', 'Unit Sekolah berhasil diperbarui.');
    }
}
