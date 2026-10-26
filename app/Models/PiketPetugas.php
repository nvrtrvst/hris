<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PiketPetugas extends Model
{
    protected $table = 'piket_petugas';

    protected $fillable = ['sesi_id', 'pegawai_id', 'area_id', 'pos_id', 'peran', 'laporan_piket_id'];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class);
    }

    public function sesi()
    {
        return $this->belongsTo(PiketSesi::class, 'sesi_id');
    }

    public function area()
    {
        return $this->belongsTo(PiketArea::class, 'area_id');
    }

    public function pos()
    {
        return $this->belongsTo(PiketPos::class, 'pos_id');
    }

    public function laporan()
    {
        return $this->belongsTo(LaporanPiket::class, 'laporan_piket_id');
    }
}
