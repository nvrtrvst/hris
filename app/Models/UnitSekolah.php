<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UnitSekolah extends Model
{
    protected $table = 'unit_sekolah';

    protected $fillable = ['nama', 'singkatan', 'latitude', 'longitude', 'radius_meter'];
}
