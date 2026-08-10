<?php

namespace App\Http\Controllers;

use App\Models\Penggajian;
use App\Traits\ResolvesPegawai;
use Illuminate\Http\Request;

class MobileGajiController extends Controller
{
    use ResolvesPegawai;

    /**
     * Daftar slip gaji milik pegawai yang login (hanya final/paid).
     */
    public function index()
    {
        $pegawai = $this->getPegawai();
        if (! $pegawai) {
            abort(403, 'Akses ditolak.');
        }

        $penggajians = Penggajian::where('pegawai_id', $pegawai->id)
            ->whereIn('status', ['finalized', 'paid'])
            ->orderBy('periode_bulan', 'desc')
            ->get(['id', 'periode_bulan', 'total_pendapatan', 'total_potongan', 'gaji_bersih', 'status']);

        return inertia('Mobile/Gaji/Index', [
            'pegawai' => $pegawai,
            'penggajians' => $penggajians,
        ]);
    }

    /**
     * Detail slip gaji milik pegawai sendiri.
     */
    public function show(Request $request, string $id)
    {
        $pegawai = $this->getPegawai();
        if (! $pegawai) {
            abort(403, 'Akses ditolak.');
        }

        $penggajian = Penggajian::with(['details', 'pegawai.units', 'pegawai.jabatans'])
            ->whereKey($id)
            ->where('pegawai_id', $pegawai->id)
            ->whereIn('status', ['finalized', 'paid'])
            ->firstOrFail();

        return inertia('Mobile/Gaji/Show', [
            'penggajian' => $penggajian,
        ]);
    }
}
