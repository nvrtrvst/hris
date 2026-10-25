<?php

$base = dirname(__DIR__);
require $base.'/vendor/autoload.php';
$app = require_once $base.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Models\Pegawai;
use Illuminate\Contracts\Console\Kernel;

$nama = $argv[1] ?? 'Ahmad';
$p = Pegawai::with(['units', 'jadwals.unitSekolah', 'statusRef'])
    ->where('nama_lengkap', 'like', "%{$nama}%")
    ->first();

if (! $p) {
    echo "NOT FOUND\n";

    return;
}

echo "Pegawai: {$p->nama_lengkap}\n";
echo 'is_tetap: '.($p->is_tetap ? 'YA' : 'TIDAK')."\n\n";

$pri = $p->units->where('pivot.is_primary', true)->first();
echo "=== UNIT ===\n";
foreach ($p->units as $u) {
    $isPri = $pri && $pri->id === $u->id;
    echo "  {$u->nama} (lat:{$u->latitude}, lng:{$u->longitude}, r:{$u->radius_meter}m)".($isPri ? ' [PRIMARY]' : '')."\n";
}

echo "\n=== JADWAL ===\n";
foreach ($p->jadwals as $j) {
    $u = $j->unitSekolah;
    $uName = $u ? $u->nama : 'NULL';
    $uLat = $u ? $u->latitude : 'NULL';
    $uLng = $u ? $u->longitude : 'NULL';
    $uRadius = $u ? $u->radius_meter : 'NULL';
    echo "  {$j->hari} {$j->jam_mulai}-{$j->jam_selesai} | unit_sekolah_id={$j->unit_sekolah_id} | {$uName} ({$uLat},{$uLng} r={$uRadius}m)\n";
}
