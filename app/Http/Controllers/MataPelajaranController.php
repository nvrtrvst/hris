<?php

namespace App\Http\Controllers;

use App\Models\MataPelajaran;
use Illuminate\Http\Request;

class MataPelajaranController extends Controller
{
    public function index()
    {
        $mapels = MataPelajaran::orderBy('nama', 'asc')->get();

        return inertia('MataPelajaran/Index', ['mapels' => $mapels]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255|unique:mata_pelajaran,nama',
        ]);

        MataPelajaran::create($validated);

        return redirect()->back()->with('message', 'Mata pelajaran berhasil ditambah.');
    }

    public function destroy($id)
    {
        MataPelajaran::destroy($id);

        return redirect()->back()->with('message', 'Mata pelajaran dihapus.');
    }
}
