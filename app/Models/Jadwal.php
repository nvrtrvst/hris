<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Jadwal extends Model
{
    protected $table = 'jadwal';

    protected $fillable = [
        'pegawai_id',
        'unit_sekolah_id',
        'kelas_id',
        'mata_pelajaran_id',
        'hari',
        'jam_mulai',
        'jam_selesai',
        'jenis_jadwal',
        'tahun_ajaran',
        'semester',
    ];

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }

    public function unitSekolah(): BelongsTo
    {
        return $this->belongsTo(UnitSekolah::class);
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class);
    }
}
