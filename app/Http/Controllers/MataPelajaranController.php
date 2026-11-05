<?php

namespace App\Http\Controllers;

use App\Models\MataPelajaran;
use App\Models\PegawaiMapel;
use Illuminate\Http\Request;

class MataPelajaranController extends Controller
{
    public function index()
    {
        $mapels = MataPelajaran::withCount('jadwals')->orderBy('nama', 'asc')->get();

        $penugasan = PegawaiMapel::with([
            'pegawai:id,nama_lengkap,nip,nuptk',
            'unitSekolah:id,nama',
        ])
            ->whereIn('mata_pelajaran_id', $mapels->pluck('id'))
            ->get()
            ->groupBy('mata_pelajaran_id');

        $mapels->each(function (MataPelajaran $mapel) use ($penugasan) {
            $mapel->setAttribute('guru', $penugasan->get($mapel->id, collect())
                ->groupBy('pegawai_id')
                ->map(function ($rows) {
                    $pegawai = $rows->first()->pegawai;

                    return [
                        'id' => $pegawai?->id,
                        'nama' => $pegawai?->nama_lengkap ?? '-',
                        'nip' => $pegawai?->nip,
                        'nuptk' => $pegawai?->nuptk,
                        'units' => $rows->pluck('unitSekolah.nama')->filter()->unique()->sort()->values()->all(),
                    ];
                })
                ->filter(fn ($guru) => $guru['id'] !== null)
                ->sortBy(fn ($guru) => mb_strtolower($guru['nama']))
                ->values()
                ->all());
        });

        $stats = [
            'total_mapel' => $mapels->count(),
            'total_penugasan_guru' => $mapels->sum(fn (MataPelajaran $mapel) => count($mapel->guru)),
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
