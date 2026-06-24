<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Kelas extends Model
{
    protected $table = 'kelas';
    protected $fillable = ['unit_sekolah_id', 'jurusan_id', 'nama', 'tingkat'];
}
