<?php

namespace App\Traits;

use App\Models\Jadwal;
use App\Models\Pegawai;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Trait untuk resolve pegawai dari user yang sedang login.
 * Digunakan di MobileController dan MobileIzinController.
 * Fallback ke Pegawai::first() HANYA di environment local/testing.
 */
trait ResolvesPegawai
{
    /**
     * Mendapatkan data Pegawai dari user yang sedang login.
     * Di production: abort(403) jika user tidak terhubung dengan pegawai.
     * Di local/testing: fallback ke pegawai yang punya jadwal hari ini untuk simulasi.
     */
    private function getPegawai(): Pegawai
    {
        $pegawai = Pegawai::where('user_id', Auth::id())->first();
        if ($pegawai) {
            return $pegawai;
        }

        // Di production, user tanpa data pegawai tidak boleh mengakses mobile.
        if (! app()->environment('local', 'testing')) {
            abort(403, 'Akses ditolak. Akun Anda tidak terhubung dengan data pegawai.');
        }

        // Fallback HANYA untuk simulasi di local:
        $hariMap = [
            'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
        ];
        $hariIniIndo = $hariMap[Carbon::now()->format('l')];

        $jadwalHariIni = Jadwal::where('hari', $hariIniIndo)->first();
        if ($jadwalHariIni) {
            return Pegawai::find($jadwalHariIni->pegawai_id);
        }

        return Pegawai::first();
    }
}
