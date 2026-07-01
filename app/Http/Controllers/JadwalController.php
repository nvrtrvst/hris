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

        // Get Pegawai for Matrix Rows
        $pegawaiQuery = Pegawai::with('units')->where('status_aktif', 'aktif');
        
        if ($user && $user->role === 'admin_unit') {
            $pegawaiQuery->whereHas('units', function($q) use ($user) {
                $q->where('unit_sekolah.id', $user->unit_sekolah_id);
            });
        } elseif ($request->filled('unit_sekolah_id')) {
            $pegawaiQuery->whereHas('units', function($q) use ($request) {
                $q->where('unit_sekolah.id', $request->unit_sekolah_id);
            });
        }
        
        $pegawais = $pegawaiQuery->orderBy('nama_lengkap')->get();

        return inertia('Jadwal/Index', [
            'jadwals' => $jadwals,
            'pegawais' => $pegawais,
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

    public function generate(Request $request)
    {
        $user = auth()->user();
        $isAdmin = $user && in_array($user->role, ['superadmin', 'admin_unit']);
        if (!$isAdmin) abort(403);

        $request->validate([
            'tahun_ajaran' => 'required|string|max:10',
            'semester' => 'required|integer|in:1,2',
            'unit_sekolah_id' => 'nullable|exists:unit_sekolah,id'
        ]);

        $unitId = $request->unit_sekolah_id;
        if ($user->role === 'admin_unit') {
            $unitId = $user->unit_sekolah_id;
        }

        // Get pegawais to generate for
        $pegawaiQuery = Pegawai::where('status_aktif', 'aktif');
        if ($unitId) {
            $pegawaiQuery->whereHas('units', function($q) use ($unitId) {
                $q->where('unit_sekolah.id', $unitId);
            });
        }
        $pegawais = $pegawaiQuery->get();

        $days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];
        $timeBlocks = [
            ['07:00', '09:00'],
            ['09:30', '11:30'],
            ['13:00', '15:00'],
        ];

        $kelas = \App\Models\Kelas::first(); // Just for dummy data
        $mapel = \App\Models\MataPelajaran::first();

        $generatedCount = 0;

        foreach ($pegawais as $pegawai) {
            $empUnitId = $unitId ?? ($pegawai->units->first()->id ?? 1);
            
            // Generate 2 random shifts for this teacher
            $assignedDays = (array) array_rand(array_flip($days), 2);
            
            foreach ($assignedDays as $day) {
                $time = $timeBlocks[array_rand($timeBlocks)];
                
                // Cek bentrok (meskipun ini random, kita cek lagi)
                $conflict = Jadwal::where('pegawai_id', $pegawai->id)
                    ->where('hari', $day)
                    ->where(function ($query) use ($time) {
                        $query->where('jam_mulai', '<', $time[1])
                              ->where('jam_selesai', '>', $time[0]);
                    })->exists();

                if (!$conflict) {
                    Jadwal::create([
                        'pegawai_id' => $pegawai->id,
                        'unit_sekolah_id' => $empUnitId,
                        'kelas_id' => $kelas ? $kelas->id : null,
                        'mata_pelajaran_id' => $mapel ? $mapel->id : null,
                        'hari' => $day,
                        'jam_mulai' => $time[0],
                        'jam_selesai' => $time[1],
                        'jenis_jadwal' => 'mengajar',
                        'tahun_ajaran' => $request->tahun_ajaran,
                        'semester' => $request->semester,
                    ]);
                    $generatedCount++;
                }
            }
        }

        return redirect()->route('jadwal.index')->with('message', "Berhasil me-generate $generatedCount jadwal acak untuk guru.");
    }

    public function swap(Request $request)
    {
        $user = auth()->user();
        $isAdmin = $user && in_array($user->role, ['superadmin', 'admin_unit']);
        if (!$isAdmin) abort(403);

        $request->validate([
            'jadwal_asal_id' => 'required|exists:jadwal,id',
            'jadwal_tujuan_id' => 'required|exists:jadwal,id|different:jadwal_asal_id',
        ]);

        $jadwalAsal = Jadwal::findOrFail($request->jadwal_asal_id);
        $jadwalTujuan = Jadwal::findOrFail($request->jadwal_tujuan_id);

        // Security check for admin_unit
        if ($user->role === 'admin_unit') {
            if ($jadwalAsal->unit_sekolah_id !== $user->unit_sekolah_id || $jadwalTujuan->unit_sekolah_id !== $user->unit_sekolah_id) {
                abort(403, 'Akses ditolak. Tidak bisa menukar lintas unit tanpa akses Superadmin.');
            }
        }

        // Cek Bentrok untuk Pegawai Asal di Jadwal Tujuan
        $conflictAsal = Jadwal::where('pegawai_id', $jadwalAsal->pegawai_id)
            ->where('hari', $jadwalTujuan->hari)
            ->where('id', '!=', $jadwalAsal->id)
            ->where(function ($query) use ($jadwalTujuan) {
                $query->where('jam_mulai', '<', $jadwalTujuan->jam_selesai)
                      ->where('jam_selesai', '>', $jadwalTujuan->jam_mulai);
            })->exists();

        if ($conflictAsal) {
            return back()->withErrors(['conflict' => 'Pertukaran gagal! Pegawai Asal memiliki jadwal yang bentrok dengan jadwal tujuan.']);
        }

        // Cek Bentrok untuk Pegawai Tujuan di Jadwal Asal
        $conflictTujuan = Jadwal::where('pegawai_id', $jadwalTujuan->pegawai_id)
            ->where('hari', $jadwalAsal->hari)
            ->where('id', '!=', $jadwalTujuan->id)
            ->where(function ($query) use ($jadwalAsal) {
                $query->where('jam_mulai', '<', $jadwalAsal->jam_selesai)
                      ->where('jam_selesai', '>', $jadwalAsal->jam_mulai);
            })->exists();

        if ($conflictTujuan) {
            return back()->withErrors(['conflict' => 'Pertukaran gagal! Pegawai Tujuan memiliki jadwal yang bentrok dengan jadwal asal.']);
        }

        // Lakukan penukaran (Hanya tukar pegawai_id)
        $tempPegawaiId = $jadwalAsal->pegawai_id;
        $jadwalAsal->update(['pegawai_id' => $jadwalTujuan->pegawai_id]);
        $jadwalTujuan->update(['pegawai_id' => $tempPegawaiId]);

        return redirect()->route('jadwal.index')->with('message', 'Pertukaran jadwal berhasil dilakukan!');
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
