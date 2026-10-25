<?php

use App\Models\Jadwal;
use App\Models\Presensi;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$pr1 = Presensi::find(1116);
echo "Presensi #1116:\n";
echo "  pegawai_id: {$pr1->pegawai_id}\n";
echo '  jadwal_id: '.($pr1->jadwal_id ?? 'NULL')."\n";
echo "  tanggal: {$pr1->tanggal}\n";
echo "  jam_masuk: {$pr1->jam_masuk}\n";
echo "  jam_keluar: {$pr1->jam_keluar}\n";
echo "  status: {$pr1->status}\n";
echo '  is_lembur: '.($pr1->is_lembur ? 'YES' : 'NO')."\n";
echo '  lembur_status: '.($pr1->lembur_status ?? 'NULL')."\n";
echo "  unit_sekolah_id: {$pr1->unit_sekolah_id}\n";
echo "  lat: {$pr1->lat}\n";
echo "  long: {$pr1->long}\n";

echo "\nPresensi #1117:\n";
$pr2 = Presensi::find(1117);
echo "  pegawai_id: {$pr2->pegawai_id}\n";
echo '  jadwal_id: '.($pr2->jadwal_id ?? 'NULL')."\n";
echo "  tanggal: {$pr2->tanggal}\n";
echo "  jam_masuk: {$pr2->jam_masuk}\n";
echo "  jam_keluar: {$pr2->jam_keluar}\n";
echo "  status: {$pr2->status}\n";
echo '  is_lembur: '.($pr2->is_lembur ? 'YES' : 'NO')."\n";
echo '  lembur_status: '.($pr2->lembur_status ?? 'NULL')."\n";
echo "  unit_sekolah_id: {$pr2->unit_sekolah_id}\n";

$j = Jadwal::find(2737);
echo "\nJadwal #2737:\n";
echo "  pegawai_id: {$j->pegawai_id}\n";
echo "  kelas_label: {$j->kelas_label}\n";
echo "  hari: {$j->hari}\n";
echo "  jam: {$j->jam_mulai}-{$j->jam_selesai}\n";
echo '  mapel: '.($j->mata_pelajaran?->nama ?? '?')."\n";
