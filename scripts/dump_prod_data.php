<?php

use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\PegawaiMapel;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$data = [
    'pegawai' => Pegawai::select('id', 'nama_lengkap')->get()->toArray(),
    'mapel' => MataPelajaran::select('id', 'nama', 'unit_sekolah_id')->get()->toArray(),
    'pml' => PegawaiMapel::select('id', 'pegawai_id', 'mata_pelajaran_id', 'unit_sekolah_id')->get()->toArray(),
];
file_put_contents(__DIR__.'/prod_dump.json', json_encode($data, JSON_UNESCAPED_UNICODE));
echo 'done: '.count($data['pegawai']).' pegawai, '.count($data['mapel']).' mapel, '.count($data['pml']).' pml';
