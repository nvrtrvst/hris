<?php

namespace App\Http\Controllers;

use App\Models\Pegawai;
use App\Models\TugasLuar;
use App\Models\UnitSekolah;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TugasLuarController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if (! $user || ! $user->can('view_presensi')) {
            abort(403);
        }

        $query = TugasLuar::with(['pegawai:id,nama_lengkap', 'unitSekolah:id,nama', 'createdBy:id,name'])
            ->orderByDesc('tanggal');

        if ($request->filled('pegawai_id')) {
            $query->where('pegawai_id', $request->pegawai_id);
        }
        if ($request->filled('tanggal')) {
            $query->where('tanggal', $request->tanggal);
        }
        if ($request->filled('unit_sekolah_id')) {
            $query->where('unit_sekolah_id', $request->unit_sekolah_id);
        } elseif ($user->unit_sekolah_id && ! $user->can('view_all_units')) {
            $query->where('unit_sekolah_id', $user->unit_sekolah_id);
        }

        $tugasLuar = $query->paginate(20)->withQueryString();

        $units = UnitSekolah::orderBy('nama')->get(['id', 'nama']);
        $pegawais = Pegawai::when($user->unit_sekolah_id && ! $user->can('view_all_units'), function ($q) use ($user) {
            $q->whereHas('units', fn ($u) => $u->where('unit_sekolah.id', $user->unit_sekolah_id));
        })->orderBy('nama_lengkap')->get(['id', 'nama_lengkap']);

        return inertia('TugasLuar/Index', [
            'tugasLuar' => $tugasLuar,
            'units' => $units,
            'pegawais' => $pegawais,
            'filters' => $request->only(['pegawai_id', 'tanggal', 'unit_sekolah_id']),
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if (! $user || ! $user->can('view_presensi')) {
            abort(403);
        }

        $data = $request->validate([
            'pegawai_id' => 'required|integer|exists:pegawai,id',
            'unit_sekolah_id' => 'nullable|integer|exists:unit_sekolah,id',
            'tanggal' => 'required|date',
            'jam_mulai' => 'nullable|date_format:H:i',
            'jam_selesai' => 'nullable|date_format:H:i|after_or_equal:jam_mulai',
            'tujuan' => 'required|string|max:255',
            'keterangan' => 'nullable|string|max:1000',
        ]);

        $pegawai = Pegawai::findOrFail($data['pegawai_id']);
        if ($user->unit_sekolah_id && ! $user->can('view_all_units') && ! $pegawai->belongsToUnit($user->unit_sekolah_id)) {
            abort(403, 'Akses ditolak.');
        }

        $data['created_by'] = $user->id;
        // Jadwal dari admin/atasan otomatis disetujui.
        TugasLuar::create($data);

        return redirect()->back()->with('message', 'Jadwal tugas luar berhasil dibuat.');
    }

    public function destroy($id)
    {
        $user = Auth::user();
        if (! $user || ! $user->can('view_presensi')) {
            abort(403);
        }

        DB::transaction(function () use ($id, $user) {
            $tugasLuar = TugasLuar::with('pegawai')->lockForUpdate()->findOrFail($id);

            if ($user->unit_sekolah_id && ! $user->can('view_all_units') && ! $tugasLuar->pegawai->belongsToUnit($user->unit_sekolah_id)) {
                abort(403, 'Akses ditolak.');
            }

            // Hapus relasi dari presensi agar tidak muncul sebagai disetujui.
            $tugasLuar->presensis()->update(['tugas_luar_id' => null, 'tugas_luar_status' => 'pending']);
            $tugasLuar->delete();
        });

        return redirect()->back()->with('message', 'Jadwal tugas luar berhasil dihapus.');
    }
}
