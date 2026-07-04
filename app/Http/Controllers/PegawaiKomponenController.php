<?php

namespace App\Http\Controllers;

use App\Models\KomponenGaji;
use App\Models\Pegawai;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Imports\PegawaiKomponenImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;

class PegawaiKomponenController extends Controller
{
    /**
     * Menampilkan daftar pegawai beserta nominal komponen gajinya.
     */
    public function index(Request $request, $komponenId)
    {
        $komponen = KomponenGaji::findOrFail($komponenId);
        
        // Ambil semua pegawai beserta nilai pivot jika ada
        $pegawais = Pegawai::where('status_aktif', 'aktif')
            ->with(['komponenGaji' => function($q) use ($komponenId) {
                $q->where('komponen_gaji_id', $komponenId);
            }, 'units'])
            ->get()
            ->map(function ($pegawai) {
                $pivot = $pegawai->komponenGaji->first();
                return [
                    'id' => $pegawai->id,
                    'nik' => $pegawai->nik,
                    'nama_lengkap' => $pegawai->nama_lengkap,
                    'unit' => $pegawai->units->first()->nama ?? '-',
                    'nominal' => $pivot ? $pivot->pivot->nominal : null,
                ];
            });

        return Inertia::render('Payroll/PegawaiKomponen', [
            'komponen' => $komponen,
            'pegawais' => $pegawais
        ]);
    }

    /**
     * Update nominal komponen gaji secara masal dari UI (Manual Input).
     */
    public function updateBatch(Request $request, $komponenId)
    {
        $komponen = KomponenGaji::findOrFail($komponenId);
        
        $request->validate([
            'pegawai_data' => 'required|array',
            'pegawai_data.*.id' => 'required|exists:pegawai,id',
            'pegawai_data.*.nominal' => 'nullable|numeric|min:0'
        ]);

        foreach ($request->pegawai_data as $data) {
            $pegawai = Pegawai::find($data['id']);
            if ($data['nominal'] !== null && $data['nominal'] !== '') {
                $pegawai->komponenGaji()->syncWithoutDetaching([
                    $komponen->id => ['nominal' => $data['nominal']]
                ]);
            } else {
                // Jika dikosongkan, hapus dari pivot agar kembali menggunakan nilai default
                $pegawai->komponenGaji()->detach($komponen->id);
            }
        }

        return redirect()->back()->with('message', 'Perubahan nominal berhasil disimpan.');
    }

    /**
     * Mengunduh template CSV/Excel untuk komponen ini.
     */
    public function downloadTemplate($komponenId)
    {
        $komponen = KomponenGaji::findOrFail($komponenId);
        $filename = "Template_Nominal_" . str_replace(' ', '_', $komponen->nama) . ".xlsx";

        return Excel::download(new \App\Exports\PegawaiKomponenExport($komponenId), $filename);
    }

    /**
     * Proses import dari Excel/CSV.
     */
    public function import(Request $request, $komponenId)
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt,xls,xlsx'
        ]);

        Excel::import(new PegawaiKomponenImport($komponenId), $request->file('file'));

        return back()->with('message', 'Data nominal berhasil di-import dari Excel.');
    }
    /**
     * Menampilkan halaman Matrix untuk edit massal semua komponen
     */
    public function matrix(Request $request)
    {
        $user = auth()->user();
        
        $query = Pegawai::where('status_aktif', 'aktif')->with(['komponenGaji', 'units']);
        if ($user && $user->unit_sekolah_id && !$user->can('view_all_units')) {
            $query->whereHas('units', function($q) use ($user) {
                $q->where('unit_sekolah.id', $user->unit_sekolah_id);
            });
        }
        $pegawais = $query->get();
        // Matrix now uses 'tampil_di_matrix' and 'urutan' for flexibility
        $komponens = KomponenGaji::where('is_active', true)
            ->where('tampil_di_matrix', true)
            ->orderBy('urutan', 'asc')
            ->get();
            
        $skalas = \App\Models\SkalaMasaBakti::orderBy('masa_kerja_tahun', 'desc')->get();
        $masaBaktiKomponens = $komponens->where('jenis', 'dinamis_masa_bakti')->pluck('id')->toArray();

        $pegawais = $pegawais->map(function($pegawai) use ($skalas, $masaBaktiKomponens) {
            $dynamic_defaults = [];
            if ($pegawai->tanggal_mulai_kerja && !empty($masaBaktiKomponens)) {
                $yearsOfService = \Carbon\Carbon::parse($pegawai->tanggal_mulai_kerja)->diffInYears(\Carbon\Carbon::now());
                $skala = $skalas->firstWhere('masa_kerja_tahun', '<=', $yearsOfService);
                $nominal = $skala ? $skala->nominal_gaji : 0;
                
                foreach($masaBaktiKomponens as $compId) {
                    $dynamic_defaults[$compId] = $nominal;
                }
            }
            $pegawai->setAttribute('dynamic_defaults', $dynamic_defaults);
            return $pegawai;
        });

        $unitSekolahs = \App\Models\UnitSekolah::all();

        return inertia('Payroll/Matrix', [
            'pegawais' => $pegawais,
            'komponens' => $komponens,
            'unitSekolahs' => $unitSekolahs
        ]);
    }

    /**
     * Menyimpan perubahan dari halaman Matrix
     */
    public function updateMatrix(Request $request)
    {
        $request->validate([
            'pegawai_data' => 'required|array',
        ]);

        DB::transaction(function() use ($request) {
            foreach ($request->pegawai_data as $data) {
                $pegawai = Pegawai::find($data['pegawai_id']);
                if (!$pegawai) continue;
                
                if (!isset($data['komponens']) || !is_array($data['komponens'])) {
                    continue;
                }

                $syncData = [];
                foreach ($data['komponens'] as $komponenId => $nominal) {
                    if ($nominal !== null && $nominal !== '') {
                        $syncData[$komponenId] = ['nominal' => preg_replace('/[^0-9]/', '', $nominal)];
                    }
                }
                
                // Hapus hanya komponen-komponen yang ada dalam matriks ini dari pivot
                $komponenIds = array_keys($data['komponens']);
                $pegawai->komponenGaji()->detach($komponenIds);
                
                // Attach kembali yang ada nilainya
                if (!empty($syncData)) {
                    $pegawai->komponenGaji()->attach($syncData);
                }
            }
        });

        return redirect()->back()->with('message', 'Perubahan matriks gaji berhasil disimpan secara massal.');
    }
}
