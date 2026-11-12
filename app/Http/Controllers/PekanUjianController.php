<?php

namespace App\Http\Controllers;

use App\Models\PekanUjian;
use App\Models\UnitSekolah;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PekanUjianController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user?->can('view_jadwal'), 403, 'Akses ditolak.');

        $unitId = $this->unitScope($user, $request);

        $periods = PekanUjian::with('unitSekolah:id,nama,singkatan')
            ->forUnit($unitId)
            ->orderByDesc('tanggal_mulai')
            ->get()
            ->map(fn (PekanUjian $pekan) => [
                'id' => $pekan->id,
                'nama' => $pekan->nama,
                'tanggal_mulai' => $pekan->tanggal_mulai->toDateString(),
                'tanggal_selesai' => $pekan->tanggal_selesai->toDateString(),
                'keterangan' => $pekan->keterangan,
                'status' => $pekan->statusPada(),
                'unit_sekolah' => $pekan->unitSekolah ? [
                    'id' => $pekan->unitSekolah->id,
                    'nama' => $pekan->unitSekolah->nama,
                    'singkatan' => $pekan->unitSekolah->singkatan,
                ] : null,
            ])
            ->values();

        $units = UnitSekolah::query()
            ->select(['id', 'nama', 'singkatan'])
            ->when($unitId !== null, fn ($q) => $q->where('id', $unitId))
            ->orderBy('nama')
            ->get();

        return Inertia::render('PekanUjian/Index', [
            'periods' => $periods,
            'units' => $units,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        abort_unless($user?->can('manage_jadwal'), 403, 'Akses ditolak.');

        $validated = $this->validatePeriod($request);
        $unitId = $this->unitScope($user, $request) ?? (int) $validated['unit_sekolah_id'];

        if ($bentrok = PekanUjian::bentrokRentang($unitId, $validated['tanggal_mulai'], $validated['tanggal_selesai'])) {
            return back()->withErrors([
                'tanggal_mulai' => "Unit ini sudah punya {$bentrok->nama} ({$bentrok->tanggal_mulai->format('d/m/Y')} - {$bentrok->tanggal_selesai->format('d/m/Y')}).",
            ])->withInput();
        }

        $pekan = PekanUjian::create([
            'unit_sekolah_id' => $unitId,
            'nama' => $validated['nama'],
            'tanggal_mulai' => $validated['tanggal_mulai'],
            'tanggal_selesai' => $validated['tanggal_selesai'],
            'keterangan' => $validated['keterangan'] ?? null,
        ]);

        $pekan->forgetJadwalCache();

        return back()->with('message', 'Pekan UAS berhasil dibuat.');
    }

    public function update(Request $request, PekanUjian $pekan_ujian)
    {
        $user = $request->user();
        abort_unless($user?->can('manage_jadwal'), 403, 'Akses ditolak.');
        $this->authorizeUnit($user, $pekan_ujian);

        if ($pekan_ujian->statusPada() === 'selesai') {
            return back()->withErrors(['nama' => 'Pekan UAS yang sudah selesai tidak dapat diubah.']);
        }

        $validated = $this->validatePeriod($request);
        $unitId = $this->unitScope($user, $request) ?? (int) $validated['unit_sekolah_id'];

        if ($bentrok = PekanUjian::bentrokRentang(
            $unitId,
            $validated['tanggal_mulai'],
            $validated['tanggal_selesai'],
            $pekan_ujian->id
        )) {
            return back()->withErrors([
                'tanggal_mulai' => "Unit ini sudah punya {$bentrok->nama} ({$bentrok->tanggal_mulai->format('d/m/Y')} - {$bentrok->tanggal_selesai->format('d/m/Y')}).",
            ])->withInput();
        }

        $unitLama = $pekan_ujian->unit_sekolah_id;

        $pekan_ujian->update([
            'unit_sekolah_id' => $unitId,
            'nama' => $validated['nama'],
            'tanggal_mulai' => $validated['tanggal_mulai'],
            'tanggal_selesai' => $validated['tanggal_selesai'],
            'keterangan' => $validated['keterangan'] ?? null,
        ]);

        if ($unitLama !== $unitId) {
            (new PekanUjian(['unit_sekolah_id' => $unitLama]))->forgetJadwalCache();
        }
        $pekan_ujian->forgetJadwalCache();

        return back()->with('message', 'Pekan UAS berhasil diperbarui.');
    }

    public function destroy(PekanUjian $pekan_ujian)
    {
        $user = request()->user();
        abort_unless($user?->can('manage_jadwal'), 403, 'Akses ditolak.');
        $this->authorizeUnit($user, $pekan_ujian);

        if ($pekan_ujian->statusPada() !== 'akan_datang') {
            return back()->withErrors(['nama' => 'Hanya pekan UAS yang belum dimulai yang dapat dihapus.']);
        }

        $pekan_ujian->jadwals()->delete();
        $pekan_ujian->delete();
        $pekan_ujian->forgetJadwalCache();

        return back()->with('message', 'Pekan UAS berhasil dihapus.');
    }

    private function validatePeriod(Request $request): array
    {
        return $request->validate([
            'unit_sekolah_id' => ['required', 'exists:unit_sekolah,id'],
            'nama' => ['required', 'string', 'max:191'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ]);
    }

    private function unitScope($user, Request $request): ?int
    {
        if ($user->unit_sekolah_id && ! $user->can('view_all_units')) {
            return (int) $user->unit_sekolah_id;
        }

        return $request->filled('unit_sekolah_id') ? $request->integer('unit_sekolah_id') : null;
    }

    private function authorizeUnit($user, PekanUjian $pekan): void
    {
        if ($user->unit_sekolah_id && ! $user->can('view_all_units')
            && (int) $pekan->unit_sekolah_id !== (int) $user->unit_sekolah_id) {
            abort(403, 'Akses ditolak.');
        }
    }
}
