<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PegawaiRiwayat extends Model
{
    protected $table = 'pegawai_riwayat';

    protected $fillable = [
        'pegawai_id',
        'jenis',
        'judul',
        'deskripsi',
        'tanggal',
    ];

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }
}
