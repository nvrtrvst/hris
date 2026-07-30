<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HariLibur extends Model
{
    protected $table = 'hari_libur';

    protected $fillable = [
        'tanggal',
        'nama',
        'unit_sekolah_id',
        'tipe',
        'keterangan',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    public function unitSekolah()
    {
        return $this->belongsTo(UnitSekolah::class);
    }

    public function scopeForUnit($query, ?int $unitId)
    {
        return $query->where(function ($q) use ($unitId) {
            $q->whereNull('unit_sekolah_id');
            if ($unitId) {
                $q->orWhere('unit_sekolah_id', $unitId);
            }
        });
    }
}
