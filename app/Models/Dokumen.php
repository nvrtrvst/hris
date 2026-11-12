<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dokumen extends Model
{
    use Auditable;

    protected $table = 'dokumen';

    protected $fillable = [
        'unit_sekolah_id',
        'judul',
        'kategori',
        'nomor',
        'penerbit',
        'tanggal_dokumen',
        'tanggal_kedaluwarsa',
        'keterangan',
        'file_path',
        'file_name',
    ];

    protected $hidden = ['file_path'];

    protected $casts = [
        'tanggal_dokumen' => 'date',
        'tanggal_kedaluwarsa' => 'date',
    ];

    public function unitSekolah(): BelongsTo
    {
        return $this->belongsTo(UnitSekolah::class);
    }

    public function getFileExtensionAttribute(): string
    {
        return strtolower(pathinfo($this->file_path, PATHINFO_EXTENSION));
    }
}
