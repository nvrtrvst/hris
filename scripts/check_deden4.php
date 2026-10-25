<?php

use App\Models\Pegawai;
use App\Models\Presensi;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$d = Pegawai::find(2277);
echo 'wajib_kantor: '.($d->wajib_kantor ? 'YES' : 'NO')."\n";
echo "status_kepegawaian: {$d->status_kepegawaian}\n";

// Cek semua kolom presensi 1116 yang relevant
$pr = Presensi::find(1116);
echo "\n=== Semua kolom Presensi #1116 ===\n";
foreach ($pr->getAttributes() as $k => $v) {
    if ($v !== null && $v !== '' && $v !== 0) {
        echo "  {$k}: {$v}\n";
    }
}

// Cek apakah ada log audit / activity log
echo "\n=== Cek created_by / user_id ===\n";
echo "  created_at: {$pr->created_at}\n";
echo "  updated_at: {$pr->updated_at}\n";

// Bandingkan dengan presensi Nunuh Nugraha yang juga jadwal_id=NULL
$nu = Presensi::find(1118);
if ($nu) {
    echo "\n=== Semua kolom Presensi #1118 (Nunuh) ===\n";
    foreach ($nu->getAttributes() as $k => $v) {
        if ($v !== null && $v !== '' && $v !== 0) {
            echo "  {$k}: {$v}\n";
        }
    }
}
