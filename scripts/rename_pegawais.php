<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$renames = [
    'Dian Siti Hardianti' => 'Dian Siti Herdiyanti',
    "D'tia Kurnia Febriyanti" => "D'tia Kurnia Febrianti",
    'Diki Fimansyah' => 'Diki Firmansyah',
    'Eli Hendrayani' => 'Elly Hendrayani',
    'Fitri Fitriyani' => 'Fitri Fitriani',
    'Gina Kusumadewi Setiawan' => 'Gina Kusumah Dewi',
    'Pina Widi Illahi' => 'Pina Widi Ilahi',
    'SOFI SOFIYANTI' => 'Sofi Sopiyanti',
];

foreach ($renames as $old => $new) {
    $count = DB::table('pegawai')->where('nama_lengkap', $old)->update(['nama_lengkap' => $new]);
    echo "$old -> $new : $count row(s) updated".PHP_EOL;
}
echo PHP_EOL.'Done.'.PHP_EOL;
