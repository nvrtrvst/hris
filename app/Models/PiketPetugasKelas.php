<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PiketPetugasKelas extends Model
{
    protected $table = 'piket_petugas_kelas';

    protected $fillable = [
        'sesi_id',
        'petugas_id',
        'dialihkan_dari',
        'kelas_label',
        'status',
    ];

    public function sesi(): BelongsTo
    {
        return $this->belongsTo(PiketSesi::class, 'sesi_id');
    }

    public function petugas(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'petugas_id');
    }

    public function dialihkanDari(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'dialihkan_dari');
    }
}
