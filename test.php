<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Exports\PegawaiKomponenExport;
use Maatwebsite\Excel\Facades\Excel;

$komponenId = 1;

use App\Imports\PegawaiKomponenImport;
use Illuminate\Support\Facades\DB;

$importer = new PegawaiKomponenImport(1);
$data = collect([
    ['nik' => '1234567819827364', 'nominal' => '500000']
]);

$importer->collection($data);
echo "Pivot table count: " . DB::table('pegawai_komponen_gaji')->count() . "\n";
echo "Pivot table data: " . DB::table('pegawai_komponen_gaji')->get()->toJson() . "\n";
