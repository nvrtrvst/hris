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
     * Baris kantor/lembur (pulang aktual, mis. realisasi izin pulang cepat)
     * menang atas baris mengajar yang jam_keluarnya prefill dari jadwal.
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
        $prioritas = [];

        $rows = Presensi::query()
            ->whereIn('pegawai_id', $items->pluck('pegawai_id')->unique()->all())
            ->whereIn('tanggal', $items->map(fn ($p) => $p->tanggal->toDateString())->unique()->all())
            ->whereNotNull('jam_keluar')
            ->orderBy('jam_keluar')
            ->get(['pegawai_id', 'tanggal', 'jam_keluar', 'is_pulang_awal', 'menit_pulang_awal', 'pulang_awal_diizinkan', 'tipe_presensi']);

        foreach ($rows as $row) {
            $key = $row->pegawai_id.'|'.$row->tanggal->toDateString();
            $bobot = $row->tipe_presensi === 'mengajar' ? 0 : 1;

            if (($prioritas[$key] ?? -1) > $bobot) {
                continue;
            }

            $prioritas[$key] = $bobot;
            $map[$key] = [
                'jam_keluar' => substr($row->jam_keluar, 0, 5),
                'is_pulang_awal' => (bool) $row->is_pulang_awal,
                'menit_pulang_awal' => $row->menit_pulang_awal,
                'pulang_awal_diizinkan' => (bool) $row->pulang_awal_diizinkan,
            ];
        }

        return $map;
    }
}
