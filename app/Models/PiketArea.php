<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PiketArea extends Model
{
    protected $table = 'piket_area';

    protected $fillable = ['unit_sekolah_id', 'nama', 'urutan', 'aktif'];

    protected $casts = ['aktif' => 'boolean', 'urutan' => 'int'];
}
