<?php

namespace App\Services;

use App\Models\PengajuanPulangCepat;
use App\Models\Presensi;

class PulangCepatRealisasi
{
    /**
     * Realisasi presensi pulang untuk sekumpulan pengajuan pulang cepat,
     * dikunci "pegawai_id|tanggal".
     *
     * Satu query untuk semua baris (daftar admin & mobile keduanya list).
     * Hari mengajar punya banyak baris presensi dan jam_keluar ditulis di baris
     * terakhir (slide pulang) - ambil baris ber-jam_keluar paling akhir.
     *
     * Jam keluar hasil pre-fill dari jadwal (unit tanpa `wajib_pulang_mengajar`
     * atau pegawai non-tetap) tidak dihitung sebagai realisasi - pengajuan tidak
     * boleh dianggap "sudah pulang" hanya karena jadwalnya berakhir.
     *
     * @param  iterable<PengajuanPulangCepat>  $pengajuans
     * @return array<string, array{jam_keluar: string, is_pulang_awal: bool, menit_pulang_awal: ?int, pulang_awal_diizinkan: bool}>
     */
    public function forPengajuans(iterable $pengajuans): array
    {
        $items = collect($pengajuans);

        if ($items->isEmpty()) {
            return [];
        }

        $map = [];

        Presensi::query()
            ->with('jadwal:id,jam_selesai')
            ->whereIn('pegawai_id', $items->pluck('pegawai_id')->unique()->all())
            ->whereIn('tanggal', $items->map(fn ($p) => $p->tanggal->toDateString())->unique()->all())
            ->whereNotNull('jam_keluar')
            ->orderBy('jam_keluar')
            ->get(['pegawai_id', 'tanggal', 'jam_keluar', 'latitude_keluar', 'jadwal_id', 'is_pulang_awal', 'menit_pulang_awal', 'pulang_awal_diizinkan'])
            ->filter(fn (Presensi $row) => $row->keluar_aktual)
            ->each(function (Presensi $row) use (&$map) {
                $map[$row->pegawai_id.'|'.$row->tanggal->toDateString()] = [
                    'jam_keluar' => substr($row->jam_keluar, 0, 5),
                    'is_pulang_awal' => (bool) $row->is_pulang_awal,
                    'menit_pulang_awal' => $row->menit_pulang_awal,
                    'pulang_awal_diizinkan' => (bool) $row->pulang_awal_diizinkan,
                ];
            });

        return $map;
    }
}
