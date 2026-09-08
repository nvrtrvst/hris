<?php

namespace App\Helpers;

use Carbon\Carbon;

class HariHelper
{
    public static function hariIniIndo()
    {
        $hariMap = [
            'Sunday' => 'Minggu',
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
        ];

        // Timezone eksplisit — dashboard/presensi membandingkan hari dengan
        // tanggal Asia/Jakarta; server dengan TZ lain (mis. UTC) akan
        // mengembalikan hari sebelumnya antara 17:00-24:00 WIB.
        return $hariMap[Carbon::now('Asia/Jakarta')->format('l')];
    }
}
