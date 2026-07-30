<?php

namespace App\Helpers;

use App\Models\Penggajian;
use Carbon\Carbon;

class PayrollLockHelper
{
    public static function isPeriodLocked(int $pegawaiId, Carbon $date): bool
    {
        $periode = $date->format('m-Y');

        return Penggajian::where('pegawai_id', $pegawaiId)
            ->where('periode_bulan', $periode)
            ->whereIn('status', ['finalized', 'paid'])
            ->exists();
    }
}
