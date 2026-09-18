<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class UnitLokasi extends Model
{
    protected $table = 'unit_lokasi';

    protected $fillable = ['unit_sekolah_id', 'nama', 'latitude', 'longitude', 'radius_meter', 'is_active'];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'is_active' => 'boolean',
    ];

    public function unitSekolah(): BelongsTo
    {
        return $this->belongsTo(UnitSekolah::class);
    }

    public function pegawais(): BelongsToMany
    {
        return $this->belongsToMany(Pegawai::class, 'pegawai_unit_lokasi')->withTimestamps();
    }
}
