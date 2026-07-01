<?php

namespace App\Http\Controllers;

use App\Models\PengajuanIzin;
use App\Models\Presensi;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class PengajuanIzinController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        if (!$user || !in_array($user->role, ['superadmin', 'admin_unit'])) {
            abort(403, 'Akses ditolak.');
        }

        $query = PengajuanIzin::with('pegawai');

        if ($user->role === 'admin_unit') {
            $query->whereHas('pegawai', function($q) use ($user) {
                $q->where('unit_sekolah_id', $user->unit_sekolah_id);
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('pegawai', function($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('nik', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status') && $request->status !== 'semua') {
            $query->where('status', $request->status);
        }

        if ($request->filled('tanggal')) {
            $query->whereDate('tanggal_mulai', '<=', $request->tanggal)
                  ->whereDate('tanggal_selesai', '>=', $request->tanggal);
        }

        $pengajuans = $query->orderBy('created_at', 'desc')->paginate(10)->withQueryString();

        return Inertia::render('PengajuanIzin/Index', [
            'pengajuans' => $pengajuans,
            'filters' => $request->only(['search', 'status', 'tanggal'])
        ]);
    }

    public function approve($id)
    {
        $user = auth()->user();
        if (!$user || !in_array($user->role, ['superadmin', 'admin_unit'])) {
            abort(403, 'Akses ditolak.');
        }

        $pengajuan = PengajuanIzin::with('pegawai')->findOrFail($id);

        // [FIX] IDOR: Pastikan admin_unit hanya bisa approve izin dari unitnya sendiri
        if ($user->role === 'admin_unit') {
            $pegawaiUnit = $pengajuan->pegawai->unit_sekolah_id ?? null;
            if ($pegawaiUnit !== $user->unit_sekolah_id) {
                abort(403, 'Akses ditolak. Anda tidak berhak menyetujui izin pegawai dari unit lain.');
            }
        }

        if ($pengajuan->status !== 'pending') {
            return back()->withErrors(['error' => 'Hanya pengajuan berstatus pending yang dapat disetujui.']);
        }

        $pengajuan->update(['status' => 'disetujui']);

        // Generate Presensi
        $period = CarbonPeriod::create($pengajuan->tanggal_mulai, $pengajuan->tanggal_selesai);
        foreach ($period as $date) {
            // Skip weekend if needed (assuming Saturday & Sunday are off, adjust if necessary)
            if ($date->isWeekend()) {
                continue;
            }

            Presensi::updateOrCreate(
                [
                    'pegawai_id' => $pengajuan->pegawai_id,
                    'tanggal' => $date->format('Y-m-d')
                ],
                [
                    'status' => $pengajuan->jenis_izin, // sakit, izin, cuti
                    'keterangan' => 'Dari Pengajuan Izin/Cuti',
                ]
            );
        }

        return back()->with('message', 'Pengajuan berhasil disetujui dan data absensi telah di-generate.');
    }

    public function reject(Request $request, $id)
    {
        $user = auth()->user();
        if (!$user || !in_array($user->role, ['superadmin', 'admin_unit'])) {
            abort(403, 'Akses ditolak.');
        }

        $pengajuan = PengajuanIzin::with('pegawai')->findOrFail($id);

        // [FIX] IDOR: Pastikan admin_unit hanya bisa reject izin dari unitnya sendiri
        if ($user->role === 'admin_unit') {
            $pegawaiUnit = $pengajuan->pegawai->unit_sekolah_id ?? null;
            if ($pegawaiUnit !== $user->unit_sekolah_id) {
                abort(403, 'Akses ditolak. Anda tidak berhak menolak izin pegawai dari unit lain.');
            }
        }
        
        $request->validate([
            'alasan_penolakan' => 'required|string|max:255'
        ]);

        $pengajuan->update([
            'status' => 'ditolak',
            'alasan_penolakan' => $request->alasan_penolakan
        ]);

        return back()->with('message', 'Pengajuan berhasil ditolak.');
    }
}
