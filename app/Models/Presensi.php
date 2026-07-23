<?php

namespace App\Models;

use App\Helpers\FileHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Presensi extends Model
{
    protected $table = 'presensi';

    protected $fillable = [
        'pegawai_id',
        'jadwal_id',
        'unit_sekolah_id',
        'tipe_presensi',
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
        'pos_a_lat',
        'pos_a_lng',
        'pos_a_accuracy',
        'pos_a_captured_at',
        'posisi_mencurigakan',
        'keterangan',
        'is_lembur',
        'lembur_status',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'lokasi_perlu_review' => 'boolean',
        'captured_at' => 'datetime',
        'pos_a_captured_at' => 'datetime',
        'posisi_mencurigakan' => 'boolean',
        'is_lembur' => 'boolean',
    ];

    public static function statusAt(string $actualTime, string $requiredTime): string
    {
        return $actualTime > $requiredTime ? 'telat' : 'hadir';
    }

    protected $appends = ['foto_masuk_url', 'foto_keluar_url'];

    public function getFotoMasukUrlAttribute(): ?string
    {
        return FileHelper::fotoUrl($this->foto_masuk);
    }

    public function getFotoKeluarUrlAttribute(): ?string
    {
        return FileHelper::fotoUrl($this->foto_keluar);
    }

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
