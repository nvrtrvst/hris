<?php

$base = dirname(__DIR__);
require $base.'/vendor/autoload.php';

$app = require_once $base.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Models\Pegawai;
use App\Models\UnitSekolah;
use Illuminate\Contracts\Console\Kernel;

echo "=== PEGAWAI DENGAN JADWAL DI UNIT BERBEDA DARI PRIMARY ===\n\n";

$pegawais = Pegawai::with(['units', 'jadwals.unitSekolah', 'statusRef'])
    ->where('status_aktif', 'aktif')
    ->has('jadwals')
    ->take(50)
    ->get()
    ->filter(fn ($p) => $p->is_tetap);

$count = 0;
foreach ($pegawais as $p) {
    $pri = $p->units->where('pivot.is_primary', true)->first();
    if (! $pri) {
        continue;
    }

    $jadwalUnitIds = $p->jadwals->pluck('unit_sekolah_id')->unique()->values();
    $diffUnits = $jadwalUnitIds->filter(fn ($uid) => $uid !== $pri->id);

    if ($diffUnits->isNotEmpty()) {
        $count++;
        echo "Pegawai: {$p->nama_lengkap}\n";
        echo "  PRIMARY: {$pri->nama} (lat:{$pri->latitude}, lng:{$pri->longitude}, r:{$pri->radius_meter}m)\n";

        foreach ($diffUnits as $uid) {
            $unit = UnitSekolah::find($uid);
            $jps = $p->jadwals->where('unit_sekolah_id', $uid);
            $jams = $jps->map(fn ($j) => "{$j->hari} {$j->jam_mulai}-{$j->jam_selesai} {$j->kelas_label}")->implode(', ');
            echo "  JADWAL BEDA: {$unit->nama} (lat:{$unit->latitude}, lng:{$unit->longitude}, r:{$unit->radius_meter}m)\n";
            echo "    {$jams}\n";
        }
        echo "\n";
    }
}

if ($count === 0) {
    echo "TIDAK ADA pegawai dengan jadwal di unit berbeda dari primary.\n";
    echo "Semua jadwal = unit primary.\n\n";
}

echo "=== TOTAL PEGAWAI TETAP: {$pegawais->count()} ===\n";
echo "=== DENGAN JADWAL BEDA UNIT: {$count} ===\n";

echo "\n=== SEMUA UNIT ===\n";
UnitSekolah::all()->each(function ($u) {
    echo "  {$u->nama} (lat:{$u->latitude}, lng:{$u->longitude}, r:{$u->radius_meter}m)\n";
});
