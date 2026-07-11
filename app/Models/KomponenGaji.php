<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KomponenGaji extends Model
{
    protected $table = 'komponen_gaji';

    protected $fillable = [
        'nama',
        'kode',
        'tipe',
        'jenis',
        'nilai_default',
        'unit_sekolah_id',
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

    public function unitSekolah()
    {
        return $this->belongsTo(UnitSekolah::class);
    }
}
