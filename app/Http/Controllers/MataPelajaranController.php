<?php

namespace App\Http\Controllers;

use App\Models\MataPelajaran;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MataPelajaranController extends Controller
{
    public function index()
    {
        $mapels = MataPelajaran::withCount(['pegawais', 'jadwals'])->orderBy('nama', 'asc')->get();

        $stats = [
            'total_mapel' => $mapels->count(),
            'total_penugasan_guru' => $mapels->sum('pegawais_count'),
            'total_jadwal' => $mapels->sum('jadwals_count'),
        ];

        return inertia('MataPelajaran/Index', [
            'mapels' => $mapels,
            'stats' => $stats,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255|unique:mata_pelajaran,nama',
        ]);

        MataPelajaran::create($validated);

        return redirect()->back()->with('message', 'Mata pelajaran berhasil ditambah.');
    }

    public function update(Request $request, MataPelajaran $mata_pelajaran)
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255', Rule::unique('mata_pelajaran', 'nama')->ignore($mata_pelajaran->id)],
        ]);

        $mata_pelajaran->update($validated);

        return redirect()->back()->with('message', 'Mata pelajaran berhasil diperbarui.');
    }

    public function destroy(MataPelajaran $mata_pelajaran)
    {
        $guruCount = $mata_pelajaran->pegawais()->count();
        if ($guruCount > 0) {
            return redirect()->back()
                ->with('error', "Mata pelajaran tidak bisa dihapus karena masih diampu oleh {$guruCount} guru.");
        }

        $jadwalCount = $mata_pelajaran->jadwals()->count();
        if ($jadwalCount > 0) {
            return redirect()->back()
                ->with('error', "Mata pelajaran tidak bisa dihapus karena masih dipakai {$jadwalCount} jadwal.");
        }

        $mata_pelajaran->delete();

        return redirect()->back()->with('message', 'Mata pelajaran dihapus.');
    }
}
