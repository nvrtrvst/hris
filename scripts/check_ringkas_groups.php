<?php

use App\Models\Pegawai;
use App\Models\Presensi;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$data = Presensi::where('tanggal', '2026-09-03')
    ->orderBy('pegawai_id')
    ->get(['id', 'pegawai_id', 'tipe_presensi', 'jadwal_id', 'jam_masuk', 'jam_keluar', 'status']);

echo 'Total: '.$data->count()." records\n";
$groups = $data->groupBy(fn ($p) => $p->pegawai_id.'__'.$p->tanggal);
echo 'Groups: '.$groups->count()."\n";
foreach ($groups as $key => $g) {
    $nama = Pegawai::find($g[0]->pegawai_id)?->nama_lengkap ?? '?';
    echo "{$nama} ({$key}): ".$g->count()." records\n";
    foreach ($g as $r) {
        echo "  id={$r->id} tipe={$r->tipe_presensi} jadwal=".($r->jadwal_id ?: 'NULL')." masuk={$r->jam_masuk} keluar=".($r->jam_keluar ?: 'NULL')." status={$r->status}\n";
    }
}

// Simulate ringkas view: how many parent rows would show?
echo "\n=== Simulated Ringkas view ===\n";
foreach ($groups as $key => $g) {
    $parent = $g->first(fn ($r) => $r->tipe_presensi === 'kantor' && ! $r->is_lembur && ! $r->is_tugas_luar) ?? $g[0];
    $children = $g->filter(fn ($r) => $r !== $parent);
    $nama = Pegawai::find($parent->pegawai_id)?->nama_lengkap ?? '?';
    echo "{$nama}: 1 parent row, ".$children->count()." children (expandable)\n";
}

// Check: are there ANY records NOT grouped? (e.g. different pegawai_id on same date)
echo "\n=== Total ringkas rows visible: ".$groups->count()." (one per pegawai per tanggal) ===\n";
