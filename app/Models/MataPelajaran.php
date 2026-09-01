<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class MataPelajaran extends Model
{
    protected $table = 'mata_pelajaran';

    protected $fillable = ['nama', 'unit_sekolah_id'];

    public function unitSekolah(): BelongsTo
    {
        return $this->belongsTo(UnitSekolah::class);
    }

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
     * Jadwal mengajar yang memakai mapel ini (via pegawai_mapel single source of truth).
     */
    public function jadwals(): HasManyThrough
    {
        return $this->hasManyThrough(
            Jadwal::class,
            PegawaiMapel::class,
            'mata_pelajaran_id',
            'pegawai_mapel_id',
            'id',
            'id',
        );
    }
}
