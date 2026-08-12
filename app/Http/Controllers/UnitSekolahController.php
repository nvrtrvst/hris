<?php

namespace App\Http\Controllers;

use App\Models\UnitSekolah;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UnitSekolahController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $query = UnitSekolah::withCount(['pegawais', 'jadwals'])->orderBy('nama');

        // admin_unit hanya melihat unitnya sendiri.
        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
            $query->where('id', $user->unit_sekolah_id);
        }

        $units = $query->get();

        $stats = [
            'total_unit' => $units->count(),
            'total_pegawai' => $units->sum('pegawais_count'),
            'total_jadwal' => $units->sum('jadwals_count'),
        ];

        return inertia('UnitSekolah/Index', [
            'units' => $units,
            'stats' => $stats,
        ]);
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
            'durasi_jp' => 'nullable|integer|min:1|max:255',
            'max_jam_minggu' => 'nullable|integer|min:1|max:168',
            'toleransi_menit' => 'nullable|integer|min:0|max:60',
            'toleransi_tap_menit' => 'nullable|integer|min:0|max:60',
            'jam_masuk_kantor' => 'required|date_format:H:i',
            'jam_pulang_kantor' => 'nullable|date_format:H:i',
        ]);

        $validated['max_jam_minggu'] = $validated['max_jam_minggu'] ?? 30;
        $validated['toleransi_tap_menit'] = $validated['toleransi_tap_menit'] ?? 15;

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
            'durasi_jp' => 'nullable|integer|min:1|max:255',
            'max_jam_minggu' => 'nullable|integer|min:1|max:168',
            'toleransi_menit' => 'nullable|integer|min:0|max:60',
            'toleransi_tap_menit' => 'nullable|integer|min:0|max:60',
            'jam_masuk_kantor' => 'required|date_format:H:i',
            'jam_pulang_kantor' => 'nullable|date_format:H:i',
        ]);

        $validated['max_jam_minggu'] = $validated['max_jam_minggu'] ?? 30;
        $validated['toleransi_tap_menit'] = $validated['toleransi_tap_menit'] ?? $unit_sekolah->toleransi_tap_menit ?? 15;

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
