<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PegawaiDokumen extends Model
{
    protected $table = 'pegawai_dokumen';

    protected $fillable = [
        'pegawai_id',
        'nama_dokumen',
        'jenis',
        'path',
        'keterangan',
    ];

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }
}
