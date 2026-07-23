<?php

namespace App\Http\Controllers;

use App\Models\Jabatan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JabatanController extends Controller
{
    public function index()
    {
        $jabatans = Jabatan::orderBy('nama')->get();

        return inertia('Jabatan/Index', [
            'jabatans' => $jabatans,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255|unique:jabatan,nama',
            'is_guru' => 'boolean',
        ]);

        Jabatan::create($validated);

        return redirect()->route('jabatan.index')
            ->with('message', 'Jabatan berhasil ditambahkan.');
    }

    public function update(Request $request, Jabatan $jabatan)
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255', Rule::unique('jabatan', 'nama')->ignore($jabatan->id)],
            'is_guru' => 'boolean',
        ]);

        $jabatan->update($validated);

        return redirect()->route('jabatan.index')
            ->with('message', 'Jabatan berhasil diperbarui.');
    }

    public function destroy(Jabatan $jabatan)
    {
        $pegawaiCount = $jabatan->pegawai()->count();
        if ($pegawaiCount > 0) {
            return redirect()->route('jabatan.index')
                ->with('error', "Jabatan tidak bisa dihapus karena masih digunakan oleh {$pegawaiCount} pegawai.");
        }

        $jabatan->delete();

        return redirect()->route('jabatan.index')
            ->with('message', 'Jabatan berhasil dihapus.');
    }
}
