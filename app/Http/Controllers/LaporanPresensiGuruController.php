<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesPimpinan;
use App\Http\Requests\PresensiGuruReportRequest;
use App\Models\Jadwal;
use App\Models\Pegawai;
use App\Models\UnitSekolah;
use App\Services\LaporanPdfService;
use App\Services\PresensiGuruReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LaporanPresensiGuruController extends Controller
{
    use ScopesPimpinan;

    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user?->can('view_presensi'), 403);

        $units = $this->units($user);
        $unitIds = $units->pluck('id')->all();

        $gurus = $this->gurus($user, $unitIds)->map(fn (Pegawai $pegawai) => [
            'id' => $pegawai->id,
            'nama' => $pegawai->nama_lengkap,
            'unit_ids' => $pegawai->units->pluck('id')->values()->all(),
            'unit_label' => $pegawai->units->pluck('nama')->implode(', '),
        ])->values();

        $years = Jadwal::whereIn('unit_sekolah_id', $unitIds)
            ->where('jenis_jadwal', 'mengajar')
            ->distinct()
            ->orderByDesc('tahun_ajaran')
            ->pluck('tahun_ajaran')
            ->filter()
            ->values();

        $now = Carbon::now();
        $defaultYear = $years->first() ?? ($now->month >= 7
            ? $now->year.'/'.($now->year + 1)
            : ($now->year - 1).'/'.$now->year);

        return Inertia::render('Laporan/PresensiGuru', [
            'units' => $units,
            'gurus' => $gurus,
            'years' => $years,
            'defaults' => [
                'unit_sekolah_id' => $units->first()?->id,
                'pegawai_id' => $gurus->first()['id'] ?? null,
                'periode' => $now->format('Y-m'),
                'tahun_ajaran' => $defaultYear,
                'semester' => $now->month >= 7 ? 1 : 2,
            ],
        ]);
    }

    public function preview(PresensiGuruReportRequest $request, PresensiGuruReportService $service)
    {
        $pegawai = $this->authorizedPegawai($request);

        return response()->json($service->build(
            $pegawai,
            UnitSekolah::findOrFail($request->validated('unit_sekolah_id')),
            $request->validated('periode'),
            $request->validated('tahun_ajaran'),
            (int) $request->validated('semester'),
        ));
    }

    public function pdf(PresensiGuruReportRequest $request, PresensiGuruReportService $service)
    {
        $pegawai = $this->authorizedPegawai($request);
        $unit = UnitSekolah::findOrFail($request->validated('unit_sekolah_id'));
        $data = $service->build(
            $pegawai,
            $unit,
            $request->validated('periode'),
            $request->validated('tahun_ajaran'),
            (int) $request->validated('semester'),
        );

        $data['unit']['logo_path'] = app(LaporanPdfService::class)->resolveUnitLogoPath($unit);

        return Pdf::loadView('exports.pdf-presensi-guru', $data)
            ->setPaper('A4', 'landscape')
            ->download('Presensi_Guru_'.$pegawai->id.'_'.$request->validated('periode').'.pdf');
    }

    private function units($user)
    {
        if ($user->can('view_all_units')) {
            return UnitSekolah::orderBy('nama')->get(['id', 'nama']);
        }

        if ($user->unit_sekolah_id) {
            return UnitSekolah::whereKey($user->unit_sekolah_id)->get(['id', 'nama']);
        }

        return UnitSekolah::whereIn('id', $user->pegawai?->units->pluck('id') ?? [])->get(['id', 'nama']);
    }

    private function gurus($user, array $unitIds)
    {
        $query = Pegawai::query()
            ->where('status_aktif', 'aktif')
            ->whereHas('units', fn ($q) => $q->whereIn('unit_sekolah.id', $unitIds))
            ->whereHas('jabatans', fn ($q) => $q->where('is_guru', true))
            ->with(['units:id,nama', 'jabatans:id,nama'])
            ->orderBy('nama_lengkap');

        if ($this->isPimpinanReadOnly($user) && ! $this->isKepsek($user)) {
            $query->where('atasan_langsung_id', $user->pegawai?->id);
        }

        return $query->get(['id', 'nama_lengkap']);
    }

    private function authorizedPegawai(PresensiGuruReportRequest $request): Pegawai
    {
        $user = $request->user();
        $unitId = (int) $request->validated('unit_sekolah_id');
        $pegawai = Pegawai::with('units')->findOrFail($request->validated('pegawai_id'));

        if (! $pegawai->units->contains('id', $unitId)) {
            abort(403, 'Guru tidak berada di unit yang dipilih.');
        }

        if ($user->can('view_all_units')) {
            return $pegawai;
        }

        if ($user->unit_sekolah_id && (int) $user->unit_sekolah_id !== $unitId) {
            abort(403, 'Unit tidak sesuai dengan akses Anda.');
        }

        if ($this->isPimpinanReadOnly($user) && ! $this->isKepsek($user)
            && (int) $pegawai->atasan_langsung_id !== (int) $user->pegawai?->id) {
            abort(403, 'Guru bukan bawahan langsung Anda.');
        }

        return $pegawai;
    }
}
