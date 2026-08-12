<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MataPelajaran extends Model
{
    protected $table = 'mata_pelajaran';

    protected $fillable = ['nama'];

    /**
     * Guru yang mengampu mapel ini (via pivot pegawai_mapel).
     */
    public function pegawais(): BelongsToMany
    {
        return $this->belongsToMany(Pegawai::class, 'pegawai_mapel')
            ->withPivot('unit_sekolah_id')
            ->withTimestamps();
    }

    /**
     * Jadwal mengajar yang memakai mapel ini.
     */
    public function jadwals(): HasMany
    {
        return $this->hasMany(Jadwal::class, 'mata_pelajaran_id');
    }
}
