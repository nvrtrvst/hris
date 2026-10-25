<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

// 1. Rename Arif
$count = DB::table('pegawai')->where('nama_lengkap', 'Arif Miftah Farid')->update(['nama_lengkap' => 'Arif Miftah Parid']);
echo "Arif Miftah Farid -> Arif Miftah Parid : $count row(s) updated".PHP_EOL;

// 2. Create missing mapel for SMK (unit_id=6)
$mapels = ['Kepala Sekolah', 'Mapel Pilihan BDP'];
foreach ($mapels as $nama) {
    $exists = DB::table('mata_pelajaran')->where('nama', $nama)->where('unit_sekolah_id', 6)->exists();
    if (! $exists) {
        DB::table('mata_pelajaran')->insert([
            'nama' => $nama,
            'unit_sekolah_id' => 6,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        echo "Created mapel: $nama".PHP_EOL;
    } else {
        echo "Mapel exists: $nama".PHP_EOL;
    }
}

echo PHP_EOL.'Done.'.PHP_EOL;
