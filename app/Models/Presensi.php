<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Presensi extends Model
{
    protected $table = 'presensi';

    protected $fillable = [
        'pegawai_id',
        'jadwal_id',
        'unit_sekolah_id',
        'tanggal',
        'jam_masuk',
        'jam_keluar',
        'status',
        'latitude_masuk',
        'longitude_masuk',
        'foto_masuk',
        'jarak_masuk_meter',
        'akurasi_masuk',
        'kecepatan_masuk',
        'latitude_keluar',
        'longitude_keluar',
        'foto_keluar',
        'jarak_keluar_meter',
        'akurasi_keluar',
        'kecepatan_keluar',
        'lokasi_perlu_review',
        'captured_at',
        'keterangan',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'lokasi_perlu_review' => 'boolean',
        'captured_at' => 'datetime',
    ];

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }

    public function jadwal(): BelongsTo
    {
        return $this->belongsTo(Jadwal::class);
    }

    public function unitSekolah(): BelongsTo
    {
        return $this->belongsTo(UnitSekolah::class);
    }
}
