<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AbsenSiswaSesi extends Model
{
    protected $table = 'absen_siswa_sesi';

    protected $fillable = [
        'unit_sekolah_id',
        'pegawai_id',
        'laporan_piket_id',
        'tanggal',
        'class_id',
        'class_label',
        'done_at',
    ];

    protected $casts = [
        'tanggal' => 'date:Y-m-d',
        'done_at' => 'datetime',
    ];

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }

    public function unitSekolah(): BelongsTo
    {
        return $this->belongsTo(UnitSekolah::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(AbsenSiswaDetail::class);
    }

    public function revisi(): HasMany
    {
        return $this->hasMany(AbsenSiswaRevisi::class, 'absen_siswa_sesi_id')->latest();
    }
}
