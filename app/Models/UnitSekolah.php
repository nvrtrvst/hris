<?php

namespace App\Models;

use App\Helpers\FileHelper;
use Illuminate\Database\Eloquent\Model;

class UnitSekolah extends Model
{
    protected $table = 'unit_sekolah';

    protected $fillable = ['nama', 'singkatan', 'logo', 'latitude', 'longitude', 'radius_meter', 'jam_masuk_kantor', 'jam_pulang_kantor'];

    protected $appends = ['logo_url'];

    public function getLogoUrlAttribute(): ?string
    {
        return FileHelper::fotoUrl($this->logo);
    }
}
