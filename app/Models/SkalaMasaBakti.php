<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkalaMasaBakti extends Model
{
    protected $table = 'skala_masa_baktis';

    protected $fillable = [
        'masa_kerja_tahun',
        'nominal_gaji',
    ];
}
