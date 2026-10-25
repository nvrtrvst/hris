<?php

use App\Models\Pegawai;
use App\Models\Presensi;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// 1. Check tipe_presensi column
$cols = DB::select("SHOW COLUMNS FROM presensi LIKE '%tipe%'");
echo "=== tipe_presensi column ===\n";
foreach ($cols as $c) {
    echo "  {$c->Field} {$c->Type} null={$c->Null}\n";
}

$cols2 = DB::select("SHOW COLUMNS FROM presensi LIKE '%presensi_key%'");
echo "=== presensi_key column ===\n";
foreach ($cols2 as $c) {
    echo "  {$c->Field} {$c->Type} null={$c->Null}\n";
}

// 2. Cek data presensi untuk beberapa pegawai dengan multiple records
$dates = DB::select("
    SELECT pegawai_id, tanggal, COUNT(*) as cnt
    FROM presensi
    WHERE tanggal = '2026-09-03'
    GROUP BY pegawai_id, tanggal
    HAVING COUNT(*) > 1
    ORDER BY cnt DESC
    LIMIT 10
");
echo "\n=== Pegawai dengan multiple presensi 2026-09-03 ===\n";
foreach ($dates as $d) {
    $nama = Pegawai::find($d->pegawai_id)?->nama_lengkap ?? '?';
    echo "  {$nama} ({$d->pegawai_id}): {$d->cnt} records\n";
    $ps = Presensi::where('pegawai_id', $d->pegawai_id)
        ->where('tanggal', '2026-09-03')
        ->get(['id', 'tipe_presensi', 'presensi_key', 'jam_masuk', 'jam_keluar', 'jadwal_id', 'status']);
    foreach ($ps as $p) {
        echo "    id={$p->id} tipe={$p->tipe_presensi} key={$p->presensi_key} masuk={$p->jam_masuk} keluar=".($p->jam_keluar ?: 'NULL').' jadwal='.($p->jadwal_id ?: 'NULL')." status={$p->status}\n";
    }
}

// 3. Cek yang tipe_presensi IS NULL
$nullTipe = Presensi::where('tanggal', '2026-09-03')
    ->whereNull('tipe_presensi')
    ->count();
echo "\n=== presensi tipe IS NULL 2026-09-03: {$nullTipe} ===\n";

$allTipe = Presensi::where('tanggal', '2026-09-03')
    ->selectRaw('tipe_presensi, COUNT(*) as cnt')
    ->groupBy('tipe_presensi')
    ->get();
echo "=== tipe_presensi distribution ===\n";
foreach ($allTipe as $t) {
    echo '  '.($t->tipe_presensi ?? 'NULL').": {$t->cnt}\n";
}
