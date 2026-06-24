<?php

namespace App\Http\Controllers;

use App\Models\Jadwal;
use App\Models\Pegawai;
use App\Models\UnitSekolah;
use Illuminate\Http\Request;

class JadwalController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $isAdmin = $user && in_array($user->role, ['superadmin', 'admin_unit']);
        $query = Jadwal::with(['pegawai', 'unitSekolah', 'kelas', 'mataPelajaran']);

        if ($user && $user->role === 'admin_unit') {
            $query->where('unit_sekolah_id', $user->unit_sekolah_id);
        } elseif (!$isAdmin) {
            $pegawai = \App\Models\Pegawai::where('user_id', auth()->id())->first();
            if ($pegawai) {
                $query->where('pegawai_id', $pegawai->id);
            } else {
                $query->where('id', -1);
            }
        } elseif ($request->filled('unit_sekolah_id')) {
            $query->where('unit_sekolah_id', $request->unit_sekolah_id);
        }

        $jadwals = $query->orderBy('hari')->orderBy('jam_mulai')->get();
        $units = UnitSekolah::all();

        return inertia('Jadwal/Index', [
            'jadwals' => $jadwals,
            'units' => $units,
            'filters' => $request->only(['unit_sekolah_id'])
        ]);
    }

    public function create()
    {
        $pegawais = Pegawai::where('status_aktif', 'aktif')->get(['id', 'nama_lengkap']);
        $units = UnitSekolah::all();
        $kelas = \App\Models\Kelas::all();
        $mapel = \App\Models\MataPelajaran::all();

        return inertia('Jadwal/Create', [
            'pegawais' => $pegawais,
            'units' => $units,
            'kelas' => $kelas,
            'mapel' => $mapel,
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        $isAdmin = $user && in_array($user->role, ['superadmin', 'admin_unit']);
        if (!$isAdmin) abort(403);

        if ($user->role === 'admin_unit') {
            $request->merge(['unit_sekolah_id' => $user->unit_sekolah_id]);
        }

        $validated = $request->validate([
            'pegawai_id' => 'required|exists:pegawai,id',
            'unit_sekolah_id' => 'required|exists:unit_sekolah,id',
            'kelas_id' => 'nullable|exists:kelas,id',
            'mata_pelajaran_id' => 'nullable|exists:mata_pelajaran,id',
            'hari' => 'required|in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu,Minggu',
            'jam_mulai' => 'required|date_format:H:i',
            'jam_selesai' => 'required|date_format:H:i|after:jam_mulai',
            'jenis_jadwal' => 'required|in:mengajar,piket,ekskul,shift_satpam,shift_kebersihan,lainnya',
            'tahun_ajaran' => 'required|string|max:10',
            'semester' => 'required|integer|in:1,2',
        ]);

        // Conflict Detection Logic (Lintas Unit)
        $conflict = Jadwal::where('pegawai_id', $validated['pegawai_id'])
            ->where('hari', $validated['hari'])
            ->where(function ($query) use ($validated) {
                $query->where(function ($q) use ($validated) {
                    $q->where('jam_mulai', '<', $validated['jam_selesai'])
                      ->where('jam_selesai', '>', $validated['jam_mulai']);
                });
            })->with('unitSekolah')->first();

        if ($conflict) {
            return back()->withErrors([
                'conflict' => "Terdeteksi bentrok jadwal! Pegawai ini sudah memiliki jadwal {$conflict->jenis_jadwal} di unit {$conflict->unitSekolah->nama} pada pukul " . substr($conflict->jam_mulai, 0, 5) . " - " . substr($conflict->jam_selesai, 0, 5)
            ])->withInput();
        }

        Jadwal::create($validated);

        return redirect()->route('jadwal.index')->with('message', 'Jadwal berhasil ditambahkan.');
    }

    public function destroy(string $id)
    {
        $user = auth()->user();
        $isAdmin = $user && in_array($user->role, ['superadmin', 'admin_unit']);
        if (!$isAdmin) abort(403);

        $jadwal = Jadwal::findOrFail($id);
        
        if ($user->role === 'admin_unit' && $jadwal->unit_sekolah_id !== $user->unit_sekolah_id) {
            abort(403, 'Akses ditolak.');
        }

        $jadwal->delete();

        return redirect()->route('jadwal.index')->with('message', 'Jadwal berhasil dihapus.');
    }
}
