<?php

namespace App\Http\Controllers;

use App\Models\Jadwal;
use App\Models\Pegawai;
use App\Models\PegawaiKelasBinaan;
use App\Models\UnitSekolah;
use App\Services\KeuanganClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WaliKelasController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->can('manage_master_data'), 403);

        $units = $this->unitsFor($user);
        $unitId = (int) ($request->integer('unit_id') ?: $units->first()?->id);
        $this->assertUnitAllowed($user, $unitId);

        $unit = $unitId ? UnitSekolah::find($unitId) : null;
        $tahunAjaran = $request->input('tahun_ajaran');
        $semester = $request->input('semester');

        $mappings = PegawaiKelasBinaan::with(['pegawai:id,nama_lengkap', 'unitSekolah:id,nama'])
            ->when($unitId, fn ($q) => $q->where('unit_sekolah_id', $unitId))
            ->when($tahunAjaran, fn ($q) => $q->where('tahun_ajaran', $tahunAjaran))
            ->when($semester, fn ($q) => $q->where('semester', $semester))
            ->orderByDesc('aktif')
            ->orderBy('class_label')
            ->get();

        $pegawais = $unitId
            ? Pegawai::where('status_aktif', 'aktif')
                ->whereHas('units', fn ($q) => $q->where('unit_sekolah.id', $unitId))
                ->orderBy('nama_lengkap')
                ->get(['id', 'nama_lengkap'])
            : collect();

        $classes = $unit
            ? (KeuanganClient::kelasByUnit($unit->nama ?: $unit->singkatan) ?? [])
            : [];

        $latestJadwal = $unitId
            ? Jadwal::where('unit_sekolah_id', $unitId)->orderByDesc('id')->first(['tahun_ajaran', 'semester'])
            : null;

        $defaults = [
            'tahun_ajaran' => $tahunAjaran ?: ($latestJadwal?->tahun_ajaran ?: date('Y').'/'.(date('Y') + 1)),
            'semester' => $semester ?: (string) ($latestJadwal?->semester ?: '1'),
        ];

        return inertia('Settings/WaliKelas', [
            'units' => $units,
            'mappings' => $mappings,
            'pegawais' => $pegawais,
            'classes' => $classes,
            'filters' => [
                'unit_id' => $unitId ?: null,
                'tahun_ajaran' => $tahunAjaran,
                'semester' => $semester,
            ],
            'defaults' => $defaults,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->can('manage_master_data'), 403);

        $validated = $request->validate([
            'unit_sekolah_id' => 'required|integer|exists:unit_sekolah,id',
            'pegawai_id' => 'required|integer|exists:pegawai,id',
            'class_id' => 'nullable|string|max:50',
            'class_label' => 'required|string|max:100',
            'tahun_ajaran' => 'required|string|max:20',
            'semester' => 'required|string|max:10',
        ]);

        $this->assertUnitAllowed($user, (int) $validated['unit_sekolah_id']);

        $pegawai = Pegawai::with('units')->findOrFail($validated['pegawai_id']);
        if ($pegawai->status_aktif !== 'aktif' || ! $pegawai->belongsToUnit((int) $validated['unit_sekolah_id'])) {
            throw ValidationException::withMessages([
                'pegawai_id' => 'Pegawai harus aktif dan menjadi anggota unit ini.',
            ]);
        }

        $classLabel = trim($validated['class_label']);
        if ($classLabel === '') {
            throw ValidationException::withMessages(['class_label' => 'Kelas wajib dipilih.']);
        }

        DB::transaction(function () use ($validated, $pegawai, $classLabel) {
            PegawaiKelasBinaan::where('unit_sekolah_id', $validated['unit_sekolah_id'])
                ->where('class_label', $classLabel)
                ->where('aktif', true)
                ->update(['aktif' => false]);

            PegawaiKelasBinaan::updateOrCreate(
                [
                    'unit_sekolah_id' => $validated['unit_sekolah_id'],
                    'class_label' => $classLabel,
                    'tahun_ajaran' => $validated['tahun_ajaran'],
                    'semester' => $validated['semester'],
                ],
                [
                    'pegawai_id' => $pegawai->id,
                    'class_id' => $validated['class_id'] ?? null,
                    'aktif' => true,
                ],
            );
        });

        return back()->with('message', 'Wali kelas disimpan.');
    }

    public function nonaktifkan(Request $request, PegawaiKelasBinaan $mapping)
    {
        $user = $request->user();
        abort_unless($user && $user->can('manage_master_data'), 403);
        $this->assertUnitAllowed($user, (int) $mapping->unit_sekolah_id);

        $mapping->update(['aktif' => false]);

        return back()->with('message', 'Mapping wali kelas dinonaktifkan.');
    }

    private function unitsFor($user)
    {
        return UnitSekolah::query()
            ->when($user->unit_sekolah_id && ! $user->can('view_all_units'), fn ($q) => $q->whereKey($user->unit_sekolah_id))
            ->orderBy('nama')
            ->get(['id', 'nama', 'singkatan']);
    }

    private function assertUnitAllowed($user, int $unitId): void
    {
        if ($unitId <= 0) {
            abort(422, 'Unit belum dipilih.');
        }

        if ($user->unit_sekolah_id && ! $user->can('view_all_units') && (int) $user->unit_sekolah_id !== $unitId) {
            abort(403, 'Akses unit ditolak.');
        }
    }
}
