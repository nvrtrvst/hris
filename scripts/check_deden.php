<?php

use App\Models\Jadwal;
use App\Models\Pegawai;
use App\Models\Presensi;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$pegawais = Pegawai::where('nama_lengkap', 'LIKE', '%Koswara%')->get(['id', 'nama_lengkap']);
echo "Pegawai:\n";
foreach ($pegawais as $p) {
    echo "  ID={$p->id} nama={$p->nama_lengkap}\n";
}

$jadwals = Jadwal::where('pegawai_id', $pegawais->pluck('id')->toArray())
    ->where('unit_sekolah_id', 6)
    ->where('tahun_ajaran', '2026/2027')
    ->where('semester', 1)
    ->where('hari', 'Rabu')
    ->get(['id', 'pegawai_id', 'kelas_label', 'hari', 'jam_mulai', 'jam_selesai', 'pegawai_mapel_id']);
echo "\nJadwal Rabu 2026/2027 S1:\n";
foreach ($jadwals as $j) {
    $mapel = $j->mata_pelajaran?->nama ?? '?';
    echo "  ID={$j->id} pmid={$j->pegawai_mapel_id} kelas={$j->kelas_label} jam={$j->jam_mulai}-{$j->jam_selesai} mapel={$mapel}\n";
}

$presensis = Presensi::where('pegawai_id', $pegawais->pluck('id')->toArray())
    ->where('tanggal', '2026-09-03')
    ->get(['id', 'pegawai_id', 'jadwal_id', 'tanggal', 'jam_masuk', 'jam_keluar', 'status', 'unit_sekolah_id']);
echo "\nPresensi 2026-09-03:\n";
foreach ($presensis as $pr) {
    $jadwal = $pr->jadwal;
    $mapel = $jadwal?->mata_pelajaran?->nama ?? '?';
    echo "  ID={$pr->id} jadwal_id={$pr->jadwal_id} jam={$pr->jam_masuk}-{$pr->jam_keluar} status={$pr->status} mapel={$mapel}\n";
}
