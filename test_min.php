<?php
use App\Jobs\ProcessPresensiFoto;
use Illuminate\Support\Facades\Bus;

$p = new stdClass();
$p->id = 1;
$peg = new stdClass();
$peg->id = 1;
$peg->nama_lengkap = "Test";

Bus::dispatchSync(new ProcessPresensiFoto(
    $p->id,
    "masuk",
    "temp.jpg",
    "presensi",
    null,
    ["id" => $peg->id, "nama" => $peg->nama_lengkap]
));

echo "OK\n";
