<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Penggajian extends Model
{
    protected $table = 'penggajian';

    protected $fillable = [
        'pegawai_id',
        'periode_bulan',
        'tanggal_generate',
        'total_pendapatan',
        'total_potongan',
        'gaji_bersih',
        'total_taxable',
        'status',
        'keterangan',
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class);
    }

    public function details()
    {
        return $this->hasMany(PenggajianDetail::class);
    }
}
