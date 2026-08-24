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
        'trajectory_samples',
        'motion_samples',
        'ip_geo',
        'exif_meta',
        'motion_suspect',
        'keterangan',
        'persentase_bayar_jam',
        'is_tugas_luar',
        'tugas_luar_status',
        'tugas_luar_id',
        'tujuan',
    ];

    protected $guarded = [
        'status',
        'is_lembur',
        'lembur_status',
        'lokasi_perlu_review',
        'posisi_mencurigakan',
        'motion_suspect',
    ];

    protected $casts = [
        'tanggal' => 'date:Y-m-d',
        'lokasi_perlu_review' => 'boolean',
        'captured_at' => 'datetime',
        'pos_a_captured_at' => 'datetime',
        'posisi_mencurigakan' => 'boolean',
        'motion_suspect' => 'boolean',
        'trajectory_samples' => 'array',
        'motion_samples' => 'array',
        'ip_geo' => 'array',
        'exif_meta' => 'array',
        'is_lembur' => 'boolean',
        'persentase_bayar_jam' => 'integer',
        'is_tugas_luar' => 'boolean',
        'foto_kegiatan' => 'array',
    ];

    public static function statusAt(string $actualTime, string $requiredTime, int $toleransiMenit = 0): string
    {
        $batasWaktu = Carbon::parse($requiredTime)->addMinutes($toleransiMenit)->format('H:i:s');

        return $actualTime > $batasWaktu ? 'telat' : 'hadir';
    }

    protected $appends = ['foto_masuk_url', 'foto_keluar_url', 'foto_kegiatan_urls'];

    public function getFotoMasukUrlAttribute(): ?string
    {
        return FileHelper::fotoUrl($this->foto_masuk);
    }

    public function getFotoKeluarUrlAttribute(): ?string
    {
        return FileHelper::fotoUrl($this->foto_keluar);
    }

    public function getFotoKegiatanUrlsAttribute(): array
    {
        $list = $this->foto_kegiatan ?? [];
        if (! is_array($list)) {
            return [];
        }

        return collect($list)
            ->map(function ($item) {
                $path = is_array($item) ? ($item['path'] ?? null) : $item;

                return $path ? FileHelper::fotoUrl($path) : null;
            })
            ->filter()
            ->values()
            ->all();
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

    public function tugasLuar(): BelongsTo
    {
        return $this->belongsTo(TugasLuar::class);
    }

    public function scopeTugasLuar($query)
    {
        return $query->where('is_tugas_luar', true);
    }
}
