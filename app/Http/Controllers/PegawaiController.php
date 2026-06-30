<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PegawaiController extends Controller
{
    public function downloadTemplate()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\PegawaiTemplateExport, 'template_pegawai.xlsx');
    }

    public function import(Request $request)
    {
        $user = auth()->user();
        $rules = [
            'file' => 'required|mimes:xlsx,xls,csv|max:5120'
        ];
        
        if ($user && $user->role !== 'admin_unit') {
            $rules['unit_sekolah_id'] = 'required|exists:unit_sekolah,id';
        }

        $request->validate($rules);
        $unitId = ($user && $user->role === 'admin_unit') ? $user->unit_sekolah_id : $request->unit_sekolah_id;

        try {
            \Illuminate\Support\Facades\DB::transaction(function() use ($request, $unitId) {
                \Maatwebsite\Excel\Facades\Excel::import(new \App\Imports\PegawaiImport($unitId), $request->file('file'));
            });
            return back()->with('message', 'Data pegawai berhasil diimport.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->with('error', 'Gagal import, periksa kembali file Anda. Terjadi kesalahan validasi baris.');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Import error: ' . $e->getMessage());
            return back()->with('error', 'Terjadi kesalahan sistem. Silakan coba lagi nanti atau hubungi administrator.');
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = \App\Models\Pegawai::with(['units', 'jabatans', 'mapels']);

        $user = auth()->user();
        if ($user && $user->role === 'admin_unit') {
            $query->whereHas('units', function($q) use ($user) {
                $q->where('unit_sekolah.id', $user->unit_sekolah_id);
            });
        } elseif ($request->filled('unit_sekolah_id')) {
            $query->whereHas('units', function($q) use ($request) {
                $q->where('unit_sekolah.id', $request->unit_sekolah_id);
            });
        }

        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('nama_lengkap', 'like', '%' . $request->search . '%')
                  ->orWhere('nik', 'like', '%' . $request->search . '%');
            });
        }

        $pegawais = $query->paginate(10)->withQueryString();

        $unitSekolahs = \App\Models\UnitSekolah::all();

        return inertia('Pegawai/Index', [
            'pegawais' => $pegawais,
            'filters' => $request->only(['search', 'unit_sekolah_id']),
            'userRole' => $user->role,
            'userUnitId' => $user->unit_sekolah_id,
            'unitSekolahs' => $unitSekolahs
        ]);
    }

    public function create()
    {
        $user = auth()->user();
        if ($user && $user->role === 'admin_unit') {
            $unitSekolahs = \App\Models\UnitSekolah::where('id', $user->unit_sekolah_id)->get();
        } else {
            $unitSekolahs = \App\Models\UnitSekolah::all();
        }
        $jabatans = \App\Models\Jabatan::all();

        return inertia('Pegawai/Create', [
            'unitSekolahs' => $unitSekolahs,
            'jabatans' => $jabatans
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        if ($user && $user->role === 'admin_unit') {
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
            'tanggal_mulai_kerja' => 'required|date',
            'pendidikan_terakhir' => 'required|string|max:255',
            'unit_sekolah_id' => 'required|exists:unit_sekolah,id',
            'jabatan_id' => 'required|exists:jabatan,id',
            'email' => 'required|email|unique:users,email',
            'password' => 'nullable|string|min:6',
            'foto' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $user = \App\Models\User::create([
            'name' => $request->nama_lengkap,
            'email' => $request->email,
            'password' => \Illuminate\Support\Facades\Hash::make($request->password ?: $request->nik),
        ]);

        $pegawaiData = collect($validated)->except(['email', 'password', 'foto'])->toArray();
        $pegawaiData['user_id'] = $user->id;

        if ($request->hasFile('foto')) {
            $path = $request->file('foto')->store('pegawai_fotos', 'public');
            $pegawaiData['foto'] = '/storage/' . $path;
        }

        $pegawai = \App\Models\Pegawai::create($pegawaiData);
        $pegawai->units()->attach($request->unit_sekolah_id, ['jabatan_id' => $request->jabatan_id, 'is_primary' => true]);

        return redirect()->route('pegawai.index')->with('message', 'Data Pegawai berhasil ditambahkan.');
    }

    public function show(string $id)
    {
        $pegawai = \App\Models\Pegawai::with(['units', 'jabatans', 'dokumen', 'riwayat', 'atasanLangsung'])->findOrFail($id);
        
        $user = auth()->user();
        if ($user && $user->role === 'admin_unit' && !$pegawai->units->pluck('id')->contains($user->unit_sekolah_id)) {
            abort(403, 'Akses ditolak.');
        }
        
        return inertia('Pegawai/Show', [
            'pegawai' => $pegawai
        ]);
    }

    public function edit(string $id)
    {
        $pegawai = \App\Models\Pegawai::with('units')->findOrFail($id);
        
        $user = auth()->user();
        if ($user && $user->role === 'admin_unit' && !$pegawai->units->pluck('id')->contains($user->unit_sekolah_id)) {
            abort(403, 'Akses ditolak.');
        }

        if ($user && $user->role === 'admin_unit') {
            $unitSekolahs = \App\Models\UnitSekolah::where('id', $user->unit_sekolah_id)->get();
        } else {
            $unitSekolahs = \App\Models\UnitSekolah::all();
        }
        $jabatans = \App\Models\Jabatan::all();

        return inertia('Pegawai/Edit', [
            'pegawai' => $pegawai,
            'unitSekolahs' => $unitSekolahs,
            'jabatans' => $jabatans
        ]);
    }

    public function update(Request $request, string $id)
    {
        $pegawai = \App\Models\Pegawai::findOrFail($id);

        $user = auth()->user();
        if ($user && $user->role === 'admin_unit' && !$pegawai->units->pluck('id')->contains($user->unit_sekolah_id)) {
            abort(403, 'Akses ditolak.');
        }

        $validated = $request->validate([
            'nik' => 'required|string|size:16|unique:pegawai,nik,' . $pegawai->id,
            'nip' => 'nullable|string|max:50|unique:pegawai,nip,' . $pegawai->id,
            'nama_lengkap' => 'required|string|max:255',
            'tempat_lahir' => 'required|string|max:255',
            'tanggal_lahir' => 'required|date',
            'jenis_kelamin' => 'required|in:L,P',
            'agama' => 'required|string|max:255',
            'status_pernikahan' => 'required|string|max:255',
            'alamat_ktp' => 'required|string',
            'no_hp' => 'required|string|max:20',
            'status_kepegawaian' => 'required|in:tetap,kontrak,honorer,gtt',
            'status_aktif' => 'required|in:aktif,cuti,nonaktif,resign',
            'tanggal_mulai_kerja' => 'required|date',
            'pendidikan_terakhir' => 'required|string|max:255',
            'foto' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $dataToUpdate = collect($validated)->except(['foto'])->toArray();

        if ($request->hasFile('foto')) {
            if ($pegawai->foto) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete(str_replace('/storage/', '', $pegawai->foto));
            }
            $path = $request->file('foto')->store('pegawai_fotos', 'public');
            $dataToUpdate['foto'] = '/storage/' . $path;
        }

        $pegawai->update($dataToUpdate);

        return redirect()->route('pegawai.show', $pegawai->id)->with('message', 'Data Pegawai berhasil diperbarui.');
    }

    public function destroy(Request $request, string $id)
    {
        $pegawai = \App\Models\Pegawai::findOrFail($id);

        $user = auth()->user();
        if ($user && $user->role === 'admin_unit' && !$pegawai->units->pluck('id')->contains($user->unit_sekolah_id)) {
            abort(403, 'Akses ditolak.');
        }
        
        $request->validate([
            'alasan_nonaktif' => 'required|string|max:255'
        ]);

        $pegawai->update([
            'status_aktif' => 'nonaktif',
            'alasan_nonaktif' => $request->alasan_nonaktif
        ]);
        
        $pegawai->delete();

        return redirect()->route('pegawai.index')->with('message', 'Data Pegawai berhasil dinonaktifkan.');
    }
}
