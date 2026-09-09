<?php

namespace App\Http\Controllers;

use App\Models\MataPelajaran;
use Illuminate\Http\Request;

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
            'nama' => [
                'required',
                'string',
                'max:255',
                // Case-insensitive — cegah duplikat "matematika" vs "Matematika".
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (MataPelajaran::whereRaw('LOWER(TRIM(nama)) = ?', [mb_strtolower(trim((string) $value))])->exists()) {
                        $fail('Mata pelajaran "'.trim((string) $value).'" sudah ada (penulisan berbeda dianggap sama).');
                    }
                },
            ],
        ]);

        MataPelajaran::create($validated);

        return redirect()->back()->with('message', 'Mata pelajaran berhasil ditambah.');
    }

    public function update(Request $request, MataPelajaran $mata_pelajaran)
    {
        $validated = $request->validate([
            'nama' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail) use ($mata_pelajaran) {
                    $exists = MataPelajaran::whereRaw('LOWER(TRIM(nama)) = ?', [mb_strtolower(trim((string) $value))])
                        ->where('id', '!=', $mata_pelajaran->id)
                        ->exists();
                    if ($exists) {
                        $fail('Mata pelajaran "'.trim((string) $value).'" sudah ada (penulisan berbeda dianggap sama).');
                    }
                },
            ],
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
