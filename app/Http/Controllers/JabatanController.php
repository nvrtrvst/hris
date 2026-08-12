<?php

namespace App\Http\Controllers;

use App\Models\Jabatan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JabatanController extends Controller
{
    public function index()
    {
        $jabatans = Jabatan::with('approverL1', 'approverL2')
            ->withCount('pegawai')
            ->orderBy('nama')
            ->get();

        $stats = [
            'total_jabatan' => $jabatans->count(),
            'total_guru' => $jabatans->where('is_guru', true)->count(),
            'total_pegawai' => $jabatans->sum('pegawai_count'),
        ];

        return inertia('Jabatan/Index', [
            'jabatans' => $jabatans,
            'stats' => $stats,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255|unique:jabatan,nama',
            'is_guru' => 'boolean',
            'approver_l1_jabatan_id' => 'nullable|exists:jabatan,id|different:approver_l2_jabatan_id',
            'approver_l2_jabatan_id' => 'nullable|exists:jabatan,id|different:approver_l1_jabatan_id',
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
            'approver_l1_jabatan_id' => 'nullable|exists:jabatan,id|different:approver_l2_jabatan_id',
            'approver_l2_jabatan_id' => 'nullable|exists:jabatan,id|different:approver_l1_jabatan_id',
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

        // Nullify approver references
        Jabatan::where('approver_l1_jabatan_id', $jabatan->id)->update(['approver_l1_jabatan_id' => null]);
        Jabatan::where('approver_l2_jabatan_id', $jabatan->id)->update(['approver_l2_jabatan_id' => null]);

        $jabatan->delete();

        return redirect()->route('jabatan.index')
            ->with('message', 'Jabatan berhasil dihapus.');
    }
}
