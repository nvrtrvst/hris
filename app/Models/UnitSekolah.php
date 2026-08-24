<?php

namespace App\Models;

use App\Helpers\FileHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitSekolah extends Model
{
    protected $table = 'unit_sekolah';

    protected $fillable = ['nama', 'singkatan', 'logo', 'latitude', 'longitude', 'radius_meter', 'durasi_jp', 'max_jam_minggu', 'toleransi_menit', 'toleransi_tap_menit', 'jam_masuk_kantor', 'jam_pulang_kantor', 'web', 'telepon', 'alamat'];

    protected $appends = ['logo_url'];

    public function getLogoUrlAttribute(): ?string
    {
        return FileHelper::fotoUrl($this->logo);
    }

    /**
     * Pegawai yang ditugaskan ke unit ini (via pivot pegawai_unit).
     */
    public function pegawais(): BelongsToMany
    {
        return $this->belongsToMany(Pegawai::class, 'pegawai_unit')
            ->withPivot(['jabatan_id', 'is_primary'])
            ->withTimestamps();
    }

    /**
     * Jadwal mengajar/reguler yang berlangsung di unit ini.
     */
    public function jadwals(): HasMany
    {
        return $this->hasMany(Jadwal::class, 'unit_sekolah_id');
    }
}
