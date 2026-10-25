<?php

use App\Models\Jadwal;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Diki Firmansyah jadwal Kamis
$jadwals = Jadwal::with('pegawaiMapel.mataPelajaran')
    ->where('pegawai_id', 2283)
    ->where('hari', 'Kamis')
    ->where('tahun_ajaran', '2026/2027')
    ->where('semester', 1)
    ->orderBy('jam_mulai')
    ->get();

echo "=== Jadwal Diki - Kamis ===\n";
foreach ($jadwals as $j) {
    $mapel = $j->pegawaiMapel?->mataPelajaran?->nama ?? '?';
    echo "  ID={$j->id} {$j->jam_mulai}-{$j->jam_selesai} mapel={$mapel} kelas={$j->kelas_label}\n";
}

// Presensi Diki 2026-09-03
$presensis = Presensi::where('pegawai_id', 2283)
    ->where('tanggal', '2026-09-03')
    ->orderBy('created_at')
    ->get();

echo "\n=== Presensi Diki - 2026-09-03 ===\n";
foreach ($presensis as $pr) {
    $jadwalInfo = '';
    if ($pr->jadwal) {
        $jadwalInfo = "jadwal_jam={$pr->jadwal->jam_mulai}-{$pr->jadwal->jam_selesai}";
    }
    echo "  ID={$pr->id} tipe={$pr->tipe_presensi} key={$pr->presensi_key}\n";
    echo '    jadwal_id='.($pr->jadwal_id ?? 'NULL')." masuk={$pr->jam_masuk} keluar=".($pr->jam_keluar ?: 'NULL')." status={$pr->status}\n";
    if ($jadwalInfo) {
        echo "    {$jadwalInfo}\n";
    }
}

// Cek: status dihitung dari jam_mulai jadwal atau dari jam_masuk absen?
// Status 'telat' = now > jadwal.jam_mulai (tanpa toleransi di storeAbsen flow kantor)
// Untuk tipe 'kantor', status dihitung dari unit.jam_masuk_kantor
echo "\n=== Unit SMK jam masuk kantor ===\n";
$unit = UnitSekolah::find(6);
echo "  jam_masuk_kantor: {$unit->jam_masuk_kantor}\n";
echo "  toleransi_menit: {$unit->toleransi_menit}\n";
