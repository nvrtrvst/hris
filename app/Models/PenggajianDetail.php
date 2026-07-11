<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PenggajianDetail extends Model
{
    protected $table = 'penggajian_detail';

    protected $fillable = [
        'penggajian_id',
        'komponen_gaji_id',
        'nama_komponen',
        'tipe',
        'nominal',
        'is_taxable',
    ];

    public function penggajian()
    {
        return $this->belongsTo(Penggajian::class);
    }

    public function komponenGaji()
    {
        return $this->belongsTo(KomponenGaji::class);
    }
}
