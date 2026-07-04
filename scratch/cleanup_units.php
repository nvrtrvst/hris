<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\UnitSekolah;
use App\Models\User;
use App\Models\Pegawai;

$duplicates = [
    6 => 2, // TK Yayasan -> TK
    7 => 3, // SD Yayasan -> SD
    8 => 4, // SMP Yayasan -> SMP
    9 => 100, // SMA Yayasan -> delete (no base SMA) or wait, 9 is SMA Yayasan. There is no SMA? Let's check: 1:LPQ, 2:TK, 3:SD, 4:SMP, 5:SMK. So SMA Yayasan has no duplicate. Maybe it should be kept? Wait, I will just delete 6, 7, 8, 10. And rename 9 to SMA.
    10 => 5 // SMK Yayasan -> SMK
];

foreach ($duplicates as $dupId => $baseId) {
    if ($baseId == 100) {
        $u = UnitSekolah::find(9);
        if ($u) {
            $u->nama = 'SMA';
            $u->save();
        }
        continue;
    }
    
    // Update Users
    User::where('unit_sekolah_id', $dupId)->update(['unit_sekolah_id' => $baseId]);
    
    // Update Pegawai_Unit pivot
    $pegawais = \Illuminate\Support\Facades\DB::table('pegawai_unit')->where('unit_sekolah_id', $dupId)->get();
    foreach ($pegawais as $p) {
        // check if baseId already exists
        $exists = \Illuminate\Support\Facades\DB::table('pegawai_unit')
            ->where('pegawai_id', $p->pegawai_id)
            ->where('unit_sekolah_id', $baseId)
            ->exists();
            
        if (!$exists) {
            \Illuminate\Support\Facades\DB::table('pegawai_unit')
                ->where('pegawai_id', $p->pegawai_id)
                ->where('unit_sekolah_id', $dupId)
                ->update(['unit_sekolah_id' => $baseId]);
        } else {
            \Illuminate\Support\Facades\DB::table('pegawai_unit')
                ->where('pegawai_id', $p->pegawai_id)
                ->where('unit_sekolah_id', $dupId)
                ->delete();
        }
    }
    
    UnitSekolah::where('id', $dupId)->delete();
}

echo "Cleanup complete!\n";
