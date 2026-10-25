<?php

use App\Models\Jadwal;
use App\Models\Presensi;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$pegawaiId = 2283; // Diki Firmansyah

// Simulate exact dashboard query
$presensiTerbaru = Presensi::with('unitSekolah')
    ->where('pegawai_id', $pegawaiId)
    ->where('tanggal', '>=', Carbon\Carbon::today()->subDays(3)->toDateString())
    ->where('tanggal', '<=', Carbon\Carbon::today()->toDateString())
    ->orderBy('tanggal', 'desc')
    ->get();

echo "=== presensiTerbaru (last 3 days) ===\n";
echo 'Count: '.$presensiTerbaru->count()."\n";
foreach ($presensiTerbaru as $p) {
    echo "  id={$p->id} tipe={$p->tipe_presensi} jadwal_id=".($p->jadwal_id ?? 'NULL')." tanggal={$p->tanggal} masuk={$p->jam_masuk}\n";
}

// Simulate what JS records.find() would do
$jadwalsHariIni = Jadwal::with(['unitSekolah', 'pegawaiMapel.mataPelajaran'])
    ->where('pegawai_id', $pegawaiId)
    ->where('hari', 'Kamis')
    ->orderBy('jam_mulai', 'asc')
    ->get();

$nowMinutes = (int) Carbon\Carbon::now()->format('H') * 60 + (int) Carbon\Carbon::now()->format('i');
$todayStr = Carbon\Carbon::now()->format('Y-m-d');

echo "\n=== Simulate Dashboard badge ===\n";
foreach ($jadwalsHariIni as $j) {
    $sudahLewat = ((int) substr($j->jam_selesai, 0, 2) * 60 + (int) substr($j->jam_selesai, 3, 2)) <= $nowMinutes;

    // This is what the JS does
    $rec = $presensiTerbaru->first(function ($r) use ($j, $todayStr) {
        return $r->jadwal_id === $j->id && str_starts_with($r->tanggal, $todayStr);
    });

    $sudahMasuk = $rec && $rec->jam_masuk;
    $selesai = $sudahMasuk && $sudahLewat;
    $bolos = $sudahLewat && ! $sudahMasuk;

    $badge = $selesai ? 'SELESAI' : ($bolos ? 'TIDAK HADIR' : ($sudahMasuk ? 'MENGAJAR' : 'BELUM'));
    $recInfo = $rec ? "FOUND (jadwal_id={$rec->jadwal_id}, jam_masuk={$rec->jam_masuk})" : 'NOT_FOUND';
    echo "jadwal={$j->id} ({$j->jam_mulai}-{$j->jam_selesai}): rec={$recInfo} badge={$badge}\n";
}

// Check: is there a type mismatch?
echo "\n=== Type check ===\n";
$firstRec = $presensiTerbaru->first();
$firstJadwal = $jadwalsHariIni->first();
echo 'presensi.jadwal_id type: '.gettype($firstRec->jadwal_id).' value='.var_export($firstRec->jadwal_id, true)."\n";
echo 'jadwal.id type: '.gettype($firstJadwal->id).' value='.var_export($firstJadwal->id, true)."\n";
echo 'strict compare: '.var_export($firstRec->jadwal_id === $firstJadwal->id, true)."\n";

// Check cache
$cacheKey = 'mobile.jadwal.'.$pegawaiId.'.Kamis';
$cached = Cache::get($cacheKey);
echo "\n=== Cache check ===\n";
echo "Cache key: {$cacheKey}\n";
echo 'Cached: '.($cached ? 'YES ('.(is_array($cached) ? 'array' : gettype($cached)).')' : 'NO')."\n";
if ($cached) {
    $cachedCollect = is_array($cached) ? collect($cached) : $cached;
    echo 'Cached jadwal count: '.$cachedCollect->count()."\n";
    $firstCached = $cachedCollect->first();
    echo 'First cached jadwal id type: '.gettype($firstCached['id'] ?? $firstCached->id ?? '?')."\n";
}
