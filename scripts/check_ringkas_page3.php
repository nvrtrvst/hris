<?php

use App\Models\Pegawai;
use App\Models\Presensi;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// 1. Total records untuk 2026-09-03
$total = Presensi::where('tanggal', '2026-09-03')->count();
echo "=== Total presensi 2026-09-03: {$total} ===\n";
echo 'Pages (paginate 10): '.ceil($total / 10)."\n\n";

// 2. Simulate page 3 (offset 20)
$page3 = Presensi::where('tanggal', '2026-09-03')
    ->orderBy('tanggal', 'desc')
    ->skip(20)->take(10)
    ->get(['id', 'pegawai_id', 'tipe_presensi', 'jadwal_id', 'jam_masuk', 'jam_keluar', 'status']);

echo "=== Page 3 records (offset 20) ===\n";
foreach ($page3 as $r) {
    $nama = Pegawai::find($r->pegawai_id)?->nama_lengkap ?? '?';
    echo "  id={$r->id} {$nama} tipe={$r->tipe_presensi} jadwal=".($r->jadwal_id ?: 'NULL')." masuk={$r->jam_masuk}\n";
}

// 3. All records untuk Ahmad A Hidayat
$ahmad = Pegawai::where('nama_lengkap', 'like', '%Ahmad%hidayat%')->first();
echo "\n=== Ahmad A Hidayat (id={$ahmad?->id}) ===\n";
if ($ahmad) {
    $recs = Presensi::where('pegawai_id', $ahmad->id)
        ->where('tanggal', '2026-09-03')
        ->orderBy('created_at')
        ->get(['id', 'tipe_presensi', 'jadwal_id', 'jam_masuk', 'jam_keluar', 'status']);
    foreach ($recs as $r) {
        echo "  id={$r->id} tipe={$r->tipe_presensi} jadwal=".($r->jadwal_id ?: 'NULL')." masuk={$r->jam_masuk} keluar=".($r->jam_keluar ?: 'NULL')." status={$r->status}\n";
    }
}

// 4. Check: all pegawai with multiple records
$multi = DB::select("
    SELECT pegawai_id, COUNT(*) as cnt
    FROM presensi
    WHERE tanggal = '2026-09-03'
    GROUP BY pegawai_id
    HAVING COUNT(*) > 1
    ORDER BY cnt DESC
");
echo "\n=== Pegawai dengan >1 record ===\n";
foreach ($multi as $m) {
    $nama = Pegawai::find($m->pegawai_id)?->nama_lengkap ?? '?';
    echo "  {$nama} ({$m->pegawai_id}): {$m->cnt} records\n";
}

// 5. Check: which page does Ahmad appear on?
if ($ahmad) {
    $ahmadPage = Presensi::where('tanggal', '2026-09-03')
        ->orderBy('tanggal', 'desc')
        ->where('pegawai_id', $ahmad->id)
        ->count();
    echo "\n=== Ahmad's position ===\n";
    $allIds = Presensi::where('tanggal', '2026-09-03')
        ->orderBy('tanggal', 'desc')
        ->pluck('id');
    $ahmadIds = Presensi::where('tanggal', '2026-09-03')
        ->where('pegawai_id', $ahmad->id)
        ->pluck('id');
    foreach ($ahmadIds as $aid) {
        $pos = $allIds->search($aid);
        $page = floor($pos / 10) + 1;
        echo "  Record ID={$aid} is at position {$pos} on page {$page}\n";
    }
}
