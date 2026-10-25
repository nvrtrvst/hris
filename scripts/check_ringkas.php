<?php

use App\Models\Pegawai;
use App\Models\Presensi;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Cari Diki Firmansyah
$diki = Pegawai::where('nama_lengkap', 'LIKE', '%Diki%Firmansyah%')->first();
if (! $diki) {
    echo "Diki not found\n";
    exit;
}
echo "Pegawai: ID={$diki->id} nama={$diki->nama_lengkap}\n";

// Semua presensi Diki 2026-09-03
$presensis = Presensi::where('pegawai_id', $diki->id)
    ->where('tanggal', '2026-09-03')
    ->orderBy('created_at')
    ->get();

echo "\n=== Presensi Diki - 2026-09-03 ({$presensis->count()} records) ===\n";
foreach ($presensis as $pr) {
    $jadwalInfo = '';
    if ($pr->jadwal) {
        $mapel = $pr->jadwal->mata_pelajaran?->nama ?? '?';
        $jadwalInfo = "jadwal_id={$pr->jadwal_id} mapel={$mapel} kelas={$pr->jadwal->kelas_label} jadwal_jam={$pr->jadwal->jam_mulai}-{$pr->jadwal->jam_selesai}";
    }
    echo "  ID={$pr->id} tipe={$pr->tipe_presensi} key={$pr->presensi_key}\n";
    echo "    masuk={$pr->jam_masuk} keluar=".($pr->jam_keluar ?: 'NULL')." status={$pr->status}\n";
    echo '    is_lembur='.($pr->is_lembur ? 'Y' : 'N').' is_tugas_luar='.($pr->is_tugas_luar ? 'Y' : 'N')."\n";
    if ($jadwalInfo) {
        echo "    {$jadwalInfo}\n";
    }
    echo "\n";
}

// Cek semua pegawai yang punya 2+ presensi di 2026-09-03 (kantor + mengajar)
$duplicates = Presensi::where('tanggal', '2026-09-03')
    ->whereIn('tipe_presensi', ['kantor', 'mengajar'])
    ->selectRaw('pegawai_id, COUNT(*) as cnt')
    ->groupBy('pegawai_id')
    ->havingRaw('COUNT(*) > 1')
    ->get();

echo "=== Pegawai dengan 2+ presensi 2026-09-03 ===\n";
foreach ($duplicates as $d) {
    $nama = Pegawai::find($d->pegawai_id)?->nama_lengkap ?? '?';
    echo "  ID={$d->pegawai_id} nama={$nama} count={$d->cnt}\n";
    $ps = Presensi::where('pegawai_id', $d->pegawai_id)->where('tanggal', '2026-09-03')->get();
    foreach ($ps as $p) {
        echo "    tipe={$p->tipe_presensi} key={$p->presensi_key} masuk={$p->jam_masuk} keluar=".($p->jam_keluar ?: 'NULL')."\n";
    }
}
