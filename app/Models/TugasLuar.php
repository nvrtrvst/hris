<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TugasLuar extends Model
{
    protected $table = 'tugas_luar';

    protected $fillable = [
        'pegawai_id',
        'unit_sekolah_id',
        'tanggal',
        'jam_mulai',
        'jam_selesai',
        'tujuan',
        'keterangan',
        'created_by',
    ];

    protected $casts = [
        'tanggal' => 'date:Y-m-d',
        'jam_mulai' => 'datetime:H:i:s',
        'jam_selesai' => 'datetime:H:i:s',
    ];

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }

    public function unitSekolah(): BelongsTo
    {
        return $this->belongsTo(UnitSekolah::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function presensis()
    {
        return $this->hasMany(Presensi::class);
    }
}
