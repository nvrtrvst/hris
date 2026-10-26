<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PiketPenugasan extends Model
{
    protected $table = 'piket_penugasan';

    protected $fillable = ['unit_sekolah_id', 'tahun_ajaran', 'semester', 'pegawai_id', 'area_id', 'pos_id', 'peran', 'aktif'];

    protected $casts = ['aktif' => 'boolean'];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class);
    }

    public function area()
    {
        return $this->belongsTo(PiketArea::class, 'area_id');
    }

    public function pos()
    {
        return $this->belongsTo(PiketPos::class, 'pos_id');
    }
}
