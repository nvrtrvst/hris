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

        return $hariMap[Carbon::now()->format('l')];
    }
}
