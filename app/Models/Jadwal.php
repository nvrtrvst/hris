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
        'kelas_label',
        'pegawai_mapel_id',
        'hari',
        'jam_mulai',
        'jam_selesai',
        'jenis_jadwal',
        'tahun_ajaran',
        'semester',
    ];

    protected $appends = ['mata_pelajaran'];

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }

    public function unitSekolah(): BelongsTo
    {
        return $this->belongsTo(UnitSekolah::class);
    }

    public function pegawaiMapel(): BelongsTo
    {
        return $this->belongsTo(PegawaiMapel::class);
    }

    public function getMataPelajaranAttribute(): ?MataPelajaran
    {
        return $this->pegawaiMapel?->mataPelajaran;
    }
}
