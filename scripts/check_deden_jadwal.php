<?php

use App\Models\Jadwal;
use App\Models\Presensi;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Jadwal Deden
$jadwals = Jadwal::with('pegawaiMapel.mataPelajaran')
    ->where('pegawai_id', 2277)
    ->where('tahun_ajaran', '2026/2027')
    ->where('semester', 1)
    ->orderBy('hari')
    ->orderBy('jam_mulai')
    ->get();

echo "=== Semua Jadwal Deden Koswara (2026/2027 S1) ===\n";
$grouped = $jadwals->groupBy('hari');
foreach ($grouped as $hari => $hs) {
    echo "\n{$hari}:\n";
    foreach ($hs as $j) {
        $mapel = $j->pegawaiMapel?->mataPelajaran?->nama ?? '?';
        $pml_id = $j->pegawai_mapel_id ?? 'NULL';
        echo "  {$j->jam_mulai}-{$j->jam_selesai} | pmid={$pml_id} | {$mapel} | {$j->kelas_label} | jenis={$j->jenis_jadwal}\n";
    }
    // Cek consecutive
    $prev = null;
    $consecutive = [];
    foreach ($hs->values() as $j) {
        if ($prev && $prev->jam_selesai === $j->jam_mulai && $prev->pegawai_mapel_id === $j->pegawai_mapel_id) {
            $consecutive[] = "{$prev->jam_mulai}-{$prev->jam_selesai} + {$j->jam_mulai}-{$j->jam_selesai}";
        }
        $prev = $j;
    }
    if ($consecutive) {
        echo '  CONSECUTIVE: '.implode(', ', $consecutive)."\n";
    }
}

// Presensi Deden 2026-09-03 (Kamis)
echo "\n=== Presensi Deden 2026-09-03 ===\n";
$presensis = Presensi::where('pegawai_id', 2277)
    ->where('tanggal', '2026-09-03')
    ->get();
foreach ($presensis as $pr) {
    echo "  ID={$pr->id} tipe={$pr->tipe_presensi} key={$pr->presensi_key} jadwal_id=".($pr->jadwal_id ?? 'NULL')." masuk={$pr->jam_masuk} keluar=".($pr->jam_keluar ?: 'NULL')." status={$pr->status}\n";
}
