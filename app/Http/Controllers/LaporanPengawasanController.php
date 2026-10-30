<?php

namespace App\Http\Controllers;

use App\Exports\LaporanPengawasanExport;
use App\Http\Requests\LaporanPengawasanRequest;
use App\Models\UnitSekolah;
use App\Services\LaporanPengawasanService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class LaporanPengawasanController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user?->can('view_presensi'), 403);

        $units = $this->units($user);
        $now = Carbon::now();
        $tahunAjaran = $now->month >= 7
            ? $now->year.'/'.($now->year + 1)
            : ($now->year - 1).'/'.$now->year;

        return Inertia::render('Laporan/Pengawasan', [
            'units' => $units,
            'defaults' => [
                'from' => $now->toDateString(),
                'to' => $now->toDateString(),
                'unit_sekolah_id' => $user->can('view_all_units') ? null : $units->first()?->id,
                'jenis_pegawai' => 'all',
                'tahun_ajaran' => $tahunAjaran,
                'semester' => $now->month >= 7 ? 1 : 2,
            ],
        ]);
    }

    public function preview(LaporanPengawasanRequest $request, LaporanPengawasanService $service)
    {
        return response()->json($service->build($request->validated(), $request->user()));
    }

    public function pdf(LaporanPengawasanRequest $request, LaporanPengawasanService $service)
    {
        $data = $service->build($request->validated(), $request->user());
        $filename = 'Laporan_Pengawasan_'.$this->slug($data).'_'.$data['period']['from'].'_to_'.$data['period']['to'].'.pdf';

        return Pdf::loadView('exports.pdf-pengawasan', $data)
            ->setPaper('A4', 'landscape')
            ->download($filename);
    }

    public function excel(LaporanPengawasanRequest $request, LaporanPengawasanService $service)
    {
        $data = $service->build($request->validated(), $request->user());
        $filename = 'Laporan_Pengawasan_'.$this->slug($data).'_'.$data['period']['from'].'_to_'.$data['period']['to'].'.xlsx';

        return Excel::download(new LaporanPengawasanExport($data), $filename);
    }

    private function units($user)
    {
        if ($user->can('view_all_units')) {
            return UnitSekolah::orderBy('nama')->get(['id', 'nama']);
        }

        if ($user->unit_sekolah_id) {
            return UnitSekolah::whereKey($user->unit_sekolah_id)->get(['id', 'nama']);
        }

        return UnitSekolah::whereIn('id', $user->pegawai?->units->pluck('id') ?? [])->orderBy('nama')->get(['id', 'nama']);
    }

    private function slug(array $data): string
    {
        return Str::slug($data['unit']['singkatan'] ?? $data['unit']['nama'] ?? 'semua_unit');
    }
}
