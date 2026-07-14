<?php

namespace App\Http\Controllers;

use App\Exports\PegawaiTemplateExport;
use App\Imports\PegawaiImport;
use App\Models\Jabatan;
use App\Models\KomponenGaji;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\UnitSekolah;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class PegawaiController extends Controller
{
    public function downloadTemplate()
    {
        return Excel::download(new PegawaiTemplateExport, 'template_pegawai.xlsx');
    }

    public function import(Request $request)
    {
        $user = auth()->user();
        $rules = [
            'file' => 'required|mimes:xlsx,xls,csv|max:5120',
        ];

        if ($user && ! $user->can('view_all_units')) {
            $rules['unit_sekolah_id'] = 'required|exists:unit_sekolah,id';
        }

        $request->validate($rules);
        $unitId = ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) ? $user->unit_sekolah_id : $request->unit_sekolah_id;

        try {
            DB::transaction(function () use ($request, $unitId) {
                Excel::import(new PegawaiImport($unitId), $request->file('file'));
            });

            return back()->with('message', 'Data pegawai berhasil diimport.');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->with('error', 'Gagal import, periksa kembali file Anda. Terjadi kesalahan validasi baris.');
        } catch (\Exception $e) {
            Log::error('Import error: '.$e->getMessage());

            return back()->with('error', 'Terjadi kesalahan sistem. Silakan coba lagi nanti atau hubungi administrator.');
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Pegawai::with(['units', 'jabatans', 'mapels', 'pengajuanIzins']);

        $user = auth()->user();
        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
            $query->whereHas('units', function ($q) use ($user) {
                $q->where('unit_sekolah.id', $user->unit_sekolah_id);
            });
        } elseif ($request->filled('unit_sekolah_id')) {
            $query->whereHas('units', function ($q) use ($request) {
                $q->where('unit_sekolah.id', $request->unit_sekolah_id);
            });
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('nama_lengkap', 'like', '%'.$request->search.'%')
                    ->orWhere('nik', 'like', '%'.$request->search.'%');
            });
        }

        if ($request->filled('mata_pelajaran_id')) {
            $query->whereHas('mapels', function ($q) use ($request) {
                $q->where('mata_pelajaran.id', $request->mata_pelajaran_id);
            });
        }

        $pegawais = $query->paginate(10)->withQueryString();

        $unitSekolahs = UnitSekolah::all();
        $mataPelajarans = MataPelajaran::all();

        return inertia('Pegawai/Index', [
            'pegawais' => $pegawais,
            'filters' => $request->only(['search', 'unit_sekolah_id', 'mata_pelajaran_id']),
            'userRole' => $user->roles->first()?->name ?? 'pegawai',
            'userUnitId' => $user->unit_sekolah_id,
            'unitSekolahs' => $unitSekolahs,
            'mataPelajarans' => $mataPelajarans,
        ]);
    }

    public function create()
    {
        $user = auth()->user();
        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
            $unitSekolahs = UnitSekolah::where('id', $user->unit_sekolah_id)->get();
        } else {
            $unitSekolahs = UnitSekolah::all();
        }
        $jabatans = Jabatan::all();

        return inertia('Pegawai/Create', [
            'unitSekolahs' => $unitSekolahs,
            'jabatans' => $jabatans,
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
            $request->merge(['unit_sekolah_id' => $user->unit_sekolah_id]);
        }

        $validated = $request->validate([
            'nik' => 'required|string|size:16|unique:pegawai,nik',
            'nip' => 'nullable|string|max:50|unique:pegawai,nip',
            'nama_lengkap' => 'required|string|max:255',
            'tempat_lahir' => 'required|string|max:255',
            'tanggal_lahir' => 'required|date',
            'jenis_kelamin' => 'required|in:L,P',
            'agama' => 'required|string|max:255',
            'status_pernikahan' => 'required|string|max:255',
            'alamat_ktp' => 'required|string',
            'no_hp' => 'required|string|max:20',
            'status_kepegawaian' => 'required|in:tetap,kontrak,honorer,gtt',
            'jatah_cuti_tahunan' => 'nullable|integer|min:0',
            'tanggal_mulai_kerja' => 'required|date',
            'pendidikan_terakhir' => 'required|string|max:255',
            'unit_sekolah_id' => 'required|exists:unit_sekolah,id',
            'jabatan_id' => 'required|exists:jabatan,id',
            'email' => 'required|email|unique:users,email',
            'password' => 'nullable|string|min:8',
            'foto' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $user = User::create([
            'name' => $request->nama_lengkap,
            'email' => $request->email,
            'username' => $request->nip ?: $request->nik,
            'password' => Hash::make($request->password ?: $request->nik),
        ]);

        $pegawaiData = collect($validated)->except(['email', 'password', 'foto'])->toArray();
        $pegawaiData['user_id'] = $user->id;

        if ($request->hasFile('foto')) {
            $path = $request->file('foto')->store('pegawai_fotos', 'public');
            $pegawaiData['foto'] = $path;
        }

        $pegawai = Pegawai::create($pegawaiData);
        $pegawai->units()->attach($request->unit_sekolah_id, ['jabatan_id' => $request->jabatan_id, 'is_primary' => true]);

        return redirect()->route('pegawai.index')->with('message', 'Data Pegawai berhasil ditambahkan.');
    }

    public function show(string $id)
    {
        $pegawai = Pegawai::with(['units', 'jabatans', 'mapels', 'dokumen', 'riwayat', 'atasanLangsung'])->findOrFail($id);

        $user = auth()->user();
        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units') && ! $pegawai->units->pluck('id')->contains($user->unit_sekolah_id)) {
            abort(403, 'Akses ditolak.');
        }

        return inertia('Pegawai/Show', [
            'pegawai' => $pegawai,
        ]);
    }

    public function edit(string $id)
    {
        $pegawai = Pegawai::with(['units', 'jabatans', 'mapels'])->findOrFail($id);

        $user = auth()->user();
        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units') && ! $pegawai->units->pluck('id')->contains($user->unit_sekolah_id)) {
            abort(403, 'Akses ditolak.');
        }

        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
            $unitSekolahs = UnitSekolah::where('id', $user->unit_sekolah_id)->get();
        } else {
            $unitSekolahs = UnitSekolah::all();
        }
        $jabatans = Jabatan::all();
        $mapels = MataPelajaran::orderBy('nama')->get();

        return inertia('Pegawai/Edit', [
            'pegawai' => $pegawai,
            'unitSekolahs' => $unitSekolahs,
            'jabatans' => $jabatans,
            'mapels' => $mapels,
        ]);
    }

    public function update(Request $request, string $id)
    {
        $pegawai = Pegawai::findOrFail($id);

        $user = auth()->user();
        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units') && ! $pegawai->units->pluck('id')->contains($user->unit_sekolah_id)) {
            abort(403, 'Akses ditolak.');
        }

        $validated = $request->validate([
            'nik' => 'required|string|size:16|unique:pegawai,nik,'.$pegawai->id,
            'nip' => 'nullable|string|max:50|unique:pegawai,nip,'.$pegawai->id,
            'nama_lengkap' => 'required|string|max:255',
            'tempat_lahir' => 'required|string|max:255',
            'tanggal_lahir' => 'required|date',
            'jenis_kelamin' => 'required|in:L,P',
            'agama' => 'required|string|max:255',
            'status_pernikahan' => 'required|string|max:255',
            'alamat_ktp' => 'required|string',
            'no_hp' => 'required|string|max:20',
            'status_kepegawaian' => 'required|in:tetap,kontrak,honorer,gtt',
            'jatah_cuti_tahunan' => 'nullable|integer|min:0',
            'status_aktif' => 'required|in:aktif,cuti,nonaktif,resign',
            'tanggal_mulai_kerja' => 'required|date',
            'pendidikan_terakhir' => 'required|string|max:255',
            'foto' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'units' => 'nullable|array',
            'units.*.unit_sekolah_id' => 'nullable|exists:unit_sekolah,id',
            'units.*.jabatan_id' => 'nullable|exists:jabatans,id',
            'units.*.is_primary' => 'nullable|boolean',
            'mapels' => 'nullable|array',
            'mapels.*.mata_pelajaran_id' => 'nullable|exists:mata_pelajaran,id',
            'mapels.*.unit_sekolah_id' => 'nullable|exists:unit_sekolah,id',
        ]);

        $dataToUpdate = collect($validated)->except(['foto'])->toArray();

        if ($request->hasFile('foto')) {
            if ($pegawai->foto) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $pegawai->foto));
            }
            $path = $request->file('foto')->store('pegawai_fotos', 'public');
            $dataToUpdate['foto'] = $path;
        }

        $pegawai->update($dataToUpdate);

        // Sinkronisasi penugasan unit + jabatan
        $syncUnits = [];
        foreach ($request->input('units', []) as $u) {
            if (empty($u['unit_sekolah_id']) || empty($u['jabatan_id'])) {
                continue;
            }
            $syncUnits[$u['unit_sekolah_id']] = [
                'jabatan_id' => $u['jabatan_id'],
                'is_primary' => !empty($u['is_primary']),
            ];
        }
        $pegawai->units()->sync($syncUnits);

        // Sinkronisasi mata pelajaran (guru)
        $syncMapels = [];
        foreach ($request->input('mapels', []) as $m) {
            if (empty($m['mata_pelajaran_id']) || empty($m['unit_sekolah_id'])) {
                continue;
            }
            $syncMapels[$m['mata_pelajaran_id']] = [
                'unit_sekolah_id' => $m['unit_sekolah_id'],
            ];
        }
        $pegawai->mapels()->sync($syncMapels);

        if ($pegawai->user_id) {
            $userAcc = User::find($pegawai->user_id);
            if ($userAcc) {
                $userAcc->update([
                    'name' => $pegawai->nama_lengkap,
                    'username' => $pegawai->nip ?: $pegawai->nik,
                ]);
            }
        }

        return redirect()->route('pegawai.show', $pegawai->id)->with('message', 'Data Pegawai berhasil diperbarui.');
    }

    public function destroy(Request $request, string $id)
    {
        $pegawai = Pegawai::findOrFail($id);

        $user = auth()->user();
        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units') && ! $pegawai->units->pluck('id')->contains($user->unit_sekolah_id)) {
            abort(403, 'Akses ditolak.');
        }

        $request->validate([
            'alasan_nonaktif' => 'required|string|max:255',
        ]);

        $pegawai->update([
            'status_aktif' => 'nonaktif',
            'alasan_nonaktif' => $request->alasan_nonaktif,
        ]);

        $pegawai->delete();

        return redirect()->route('pegawai.index')->with('message', 'Data Pegawai berhasil dinonaktifkan.');
    }

    /**
     * Menampilkan profil keuangan khusus (Tab Keuangan)
     */
    public function keuangan($id)
    {
        $pegawai = Pegawai::with('komponenGaji')->findOrFail($id);

        $user = auth()->user();
        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units') && ! $pegawai->units->pluck('id')->contains($user->unit_sekolah_id)) {
            abort(403, 'Akses ditolak.');
        }

        $komponens = KomponenGaji::where('is_active', true)->get();

        return inertia('Pegawai/Keuangan', [
            'pegawai' => $pegawai,
            'komponens' => $komponens,
        ]);
    }

    /**
     * Menyimpan profil keuangan khusus
     */
    public function updateKeuangan(Request $request, $id)
    {
        $pegawai = Pegawai::findOrFail($id);

        $user = auth()->user();
        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units') && ! $pegawai->units->pluck('id')->contains($user->unit_sekolah_id)) {
            abort(403, 'Akses ditolak.');
        }

        $request->validate([
            'komponens' => 'nullable|array',
        ]);

        $syncData = [];
        if ($request->has('komponens')) {
            foreach ($request->komponens as $komponenId => $nominal) {
                if ($nominal !== null && $nominal !== '') {
                    $syncData[$komponenId] = ['nominal' => preg_replace('/[^0-9]/', '', $nominal)];
                }
            }
        }

        $pegawai->komponenGaji()->sync($syncData);

        return redirect()->back()->with('message', 'Profil Keuangan Pegawai berhasil diperbarui.');
    }
}
