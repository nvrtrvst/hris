<?php

use App\Models\Jadwal;
use App\Models\Pegawai;
use App\Models\PegawaiMapel;
use App\Models\Presensi;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// 1. Jadwal Deden untuk Kamis (2026-09-03)
$jadwals = Jadwal::where('pegawai_id', 2277)
    ->where('hari', 'Kamis')
    ->where('tahun_ajaran', '2026/2027')
    ->where('semester', 1)
    ->orderBy('jam_mulai')
    ->get(['id', 'kelas_label', 'hari', 'jam_mulai', 'jam_selesai', 'pegawai_mapel_id', 'jenis_jadwal']);

echo "=== Jadwal Deden - Kamis ===\n";
foreach ($jadwals as $j) {
    $mapel = PegawaiMapel::find($j->pegawai_mapel_id)?->mataPelajaran?->nama ?? '?';
    echo "  ID={$j->id} kelas={$j->kelas_label} jam={$j->jam_mulai}-{$j->jam_selesai} mapel={$mapel} jenis={$j->jenis_jadwal}\n";
}

// 2. Semua presensi Deden 2026-09-03
$presensis = Presensi::where('pegawai_id', 2277)
    ->where('tanggal', '2026-09-03')
    ->get();

echo "\n=== Presensi Deden - 2026-09-03 ===\n";
foreach ($presensis as $pr) {
    echo "  ID={$pr->id} jadwal_id=".($pr->jadwal_id ?? 'NULL')." jam={$pr->jam_masuk}-{$pr->jam_keluar} status={$pr->status} is_lembur=".($pr->is_lembur ? 'Y' : 'N').' lat='.($pr->lat ?? 'null').' long='.($pr->long ?? 'null').' captured_at='.($pr->captured_at ?? 'null')."\n";
}

// 3. Cek apakah presensi #1116 punya captured_at / akurasi
$pr1 = Presensi::find(1116);
echo "\n=== Detail Presensi #1116 ===\n";
echo "  created_at: {$pr1->created_at}\n";
echo "  updated_at: {$pr1->updated_at}\n";
echo '  captured_at: '.($pr1->captured_at ?? 'null')."\n";
echo '  akurasi_masuk: '.($pr1->akurasi_masuk ?? 'null')."\n";
echo '  jarak_meter: '.($pr1->jarak_meter ?? 'null')."\n";
echo '  foto: '.($pr1->foto ?? 'null')."\n";

// 4. Cek presensi lain di unit 6 pada waktu yang sama (08:03-08:05)
$samewakture = Presensi::where('unit_sekolah_id', 6)
    ->where('tanggal', '2026-09-03')
    ->where('jam_masuk', '>=', '08:00')
    ->where('jam_masuk', '<=', '08:10')
    ->where('id', '!=', 1116)
    ->get(['id', 'pegawai_id', 'jadwal_id', 'jam_masuk', 'status']);

echo "\n=== Presensi lain jam 08:00-08:10 di SMK ===\n";
foreach ($samewakture as $s) {
    $nama = Pegawai::find($s->pegawai_id)?->nama_lengkap ?? '?';
    echo "  ID={$s->id} pegawai={$nama} jadwal_id=".($s->jadwal_id ?? 'NULL')." jam={$s->jam_masuk} status={$s->status}\n";
}
