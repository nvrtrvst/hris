<?php

namespace App\Models;

use App\Helpers\FileHelper;
use Carbon\Carbon;
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
        'latitude_masuk',
        'longitude_masuk',
        'foto_masuk',
        'foto_masuk_status',
        'foto_masuk_error',
        'jarak_masuk_meter',
        'akurasi_masuk',
        'kecepatan_masuk',
        'latitude_keluar',
        'longitude_keluar',
        'foto_keluar',
        'foto_keluar_status',
        'foto_keluar_error',
        'jarak_keluar_meter',
        'akurasi_keluar',
        'kecepatan_keluar',
        'captured_at',
        'pos_a_lat',
        'pos_a_lng',
        'pos_a_accuracy',
        'pos_a_captured_at',
        'keterangan',
        'persentase_bayar_jam',
    ];

    protected $guarded = [
        'status',
        'is_lembur',
        'lembur_status',
        'lokasi_perlu_review',
        'posisi_mencurigakan',
    ];

    protected $casts = [
        'tanggal' => 'date:Y-m-d',
        'lokasi_perlu_review' => 'boolean',
        'captured_at' => 'datetime',
        'pos_a_captured_at' => 'datetime',
        'posisi_mencurigakan' => 'boolean',
        'is_lembur' => 'boolean',
        'persentase_bayar_jam' => 'integer',
    ];

    public static function statusAt(string $actualTime, string $requiredTime, int $toleransiMenit = 0): string
    {
        $batasWaktu = Carbon::parse($requiredTime)->addMinutes($toleransiMenit)->format('H:i:s');

        return $actualTime > $batasWaktu ? 'telat' : 'hadir';
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
