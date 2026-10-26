<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PiketPos extends Model
{
    protected $table = 'piket_pos';
    protected $fillable = ['unit_sekolah_id', 'area_id', 'nama', 'jam_mulai', 'jam_selesai', 'is_wajib', 'aktif'];
    protected $casts = ['is_wajib' => 'boolean', 'aktif' => 'boolean'];
}
