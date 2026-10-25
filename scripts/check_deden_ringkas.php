<?php

use App\Models\Presensi;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$pegawaiId = 2277;

$recs = Presensi::where('pegawai_id', $pegawaiId)
    ->where('tanggal', '2026-09-03')
    ->orderBy('created_at')
    ->get(['id', 'tipe_presensi', 'jadwal_id', 'jam_masuk', 'jam_keluar', 'status']);

echo "=== Semua presensi Deden 2026-09-03 ===\n";
foreach ($recs as $r) {
    echo "  id={$r->id} tipe={$r->tipe_presensi} jadwal=".($r->jadwal_id ?: 'NULL')." masuk={$r->jam_masuk} keluar=".($r->jam_keluar ?: 'NULL')." status={$r->status}\n";
}

echo "\nTotal: ".$recs->count()." records\n";
$kantor = $recs->where('tipe_presensi', 'kantor');
$mengajar = $recs->where('tipe_presensi', 'mengajar');
echo 'Kantor: '.$kantor->count()." records\n";
echo 'Mengajar: '.$mengajar->count()." records\n";

// Simulate Ringkas parent selection
$parent = $recs->first(fn ($r) => $r->tipe_presensi === 'kantor' && ! $r->is_lembur && ! $r->is_tugas_luar) ?? $recs->first();
$children = $recs->filter(fn ($r) => $r !== $parent);

echo "\n=== Ringkas grouping ===\n";
echo "Parent: id={$parent->id} tipe={$parent->tipe_presensi} masuk={$parent->jam_masuk}\n";
echo 'Children: '.$children->count()." records\n";
foreach ($children as $c) {
    echo "  id={$c->id} tipe={$c->tipe_presensi} jadwal=".($c->jadwal_id ?: 'NULL')." masuk={$c->jam_masuk} status={$c->status}\n";
}
