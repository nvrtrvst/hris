<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PegawaiKelasBinaan extends Model
{
    protected $table = 'pegawai_kelas_binaan';

    protected $fillable = [
        'pegawai_id',
        'unit_sekolah_id',
        'class_id',
        'class_label',
        'tahun_ajaran',
        'semester',
        'aktif',
    ];

    protected $casts = ['aktif' => 'boolean'];

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }

    public function unitSekolah(): BelongsTo
    {
        return $this->belongsTo(UnitSekolah::class);
    }

    public function scopeAktif($query)
    {
        return $query->where('aktif', true);
    }
}
