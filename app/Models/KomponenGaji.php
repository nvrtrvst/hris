<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KomponenGaji extends Model
{
    protected $table = 'komponen_gaji';

    protected $fillable = [
        'nama',
        'tipe',
        'jenis',
        'nilai_default',
        'is_taxable',
        'is_active',
        'urutan',
        'tampil_di_matrix',
    ];

    public function pegawais()
    {
        return $this->belongsToMany(Pegawai::class, 'pegawai_komponen_gaji')
                    ->withPivot('nominal')
                    ->withTimestamps();
    }
}
