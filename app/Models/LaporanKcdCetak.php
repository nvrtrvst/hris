<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaporanKcdCetak extends Model
{
    protected $table = 'laporan_kcd_cetak';

    protected $fillable = [
        'user_id',
        'unit_sekolah_id',
        'periode_key',
        'minggu',
        'start_date',
        'end_date',
        'nomor_cetak',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'minggu' => 'integer',
        'nomor_cetak' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function unitSekolah(): BelongsTo
    {
        return $this->belongsTo(UnitSekolah::class, 'unit_sekolah_id');
    }
}
