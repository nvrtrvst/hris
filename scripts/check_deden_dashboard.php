<?php

use App\Models\Jadwal;
use App\Models\Presensi;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$pegawaiId = 2277; // Deden Koswara

// 1. Jadwal Deden Kamis
$jadwals = Jadwal::with('pegawaiMapel.mataPelajaran')
    ->where('pegawai_id', $pegawaiId)
    ->where('hari', 'Kamis')
    ->where('tahun_ajaran', '2026/2027')
    ->where('semester', 1)
    ->orderBy('jam_mulai')
    ->get();

echo "=== Jadwal Deden - Kamis ===\n";
foreach ($jadwals as $j) {
    $mapel = $j->pegawaiMapel?->mataPelajaran?->nama ?? '?';
    echo "  ID={$j->id} {$j->jam_mulai}-{$j->jam_selesai} mapel={$mapel} kelas={$j->kelas_label}\n";
}

// 2. Presensi Deden 2026-09-03
$recs = Presensi::where('pegawai_id', $pegawaiId)
    ->where('tanggal', '2026-09-03')
    ->get(['id', 'tipe_presensi', 'jadwal_id', 'jam_masuk', 'jam_keluar', 'status']);

echo "\n=== Presensi Deden - 2026-09-03 ===\n";
foreach ($recs as $r) {
    echo "  id={$r->id} tipe={$r->tipe_presensi} jadwal=".($r->jadwal_id ?: 'NULL')." masuk={$r->jam_masuk} keluar=".($r->jam_keluar ?: 'NULL')." status={$r->status}\n";
}

// 3. Simulate Dashboard badge
$presensiTerbaru = Presensi::where('pegawai_id', $pegawaiId)
    ->where('tanggal', '>=', Carbon\Carbon::today()->subDays(3)->toDateString())
    ->where('tanggal', '<=', Carbon\Carbon::today()->toDateString())
    ->orderBy('tanggal', 'desc')
    ->get();

$nowMinutes = (int) Carbon\Carbon::now()->format('H') * 60 + (int) Carbon\Carbon::now()->format('i');
$todayStr = Carbon\Carbon::now()->format('Y-m-d');

echo "\n=== Simulate Mobile Dashboard badge ===\n";
foreach ($jadwals as $j) {
    $sudahLewat = ((int) substr($j->jam_selesai, 0, 2) * 60 + (int) substr($j->jam_selesai, 3, 2)) <= $nowMinutes;
    $rec = $presensiTerbaru->first(fn ($r) => $r->jadwal_id === $j->id && str_starts_with($r->tanggal, $todayStr));
    $sudahMasuk = $rec && $rec->jam_masuk;
    $selesai = $sudahMasuk && $sudahLewat;
    $bolos = $sudahLewat && ! $sudahMasuk;

    $badge = $selesai ? 'SELESAI' : ($bolos ? 'TIDAK HADIR' : ($sudahMasuk ? 'MENGAJAR' : 'BELUM'));
    $recInfo = $rec ? "FOUND jam_masuk={$rec->jam_masuk}" : 'NOT_FOUND';
    echo "jadwal={$j->id} ({$j->jam_mulai}-{$j->jam_selesai}): rec={$recInfo} badge={$badge}\n";
}
