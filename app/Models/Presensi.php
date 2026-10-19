<?php

namespace App\Models;

use App\Helpers\FileHelper;
use App\Services\PulangAwalDetector;
use App\Services\PulangAwalNotifier;
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
        'unit_lokasi_masuk_id',
        'unit_lokasi_keluar_id',
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
        'is_pulang_awal',
        'menit_pulang_awal',
        'pulang_awal_diizinkan',
    ];

    protected static function booted(): void
    {
        // Deteksi pulang awal tiap jam_keluar berubah (skip bila bukan via query-builder).
        static::saving(function (Presensi $presensi) {
            if ($presensi->isDirty('jam_keluar')) {
                app(PulangAwalDetector::class)->apply($presensi);
            }
        });

        // Kabari atasan saat pulang awal tanpa izin (dedup harian ada di notifier).
        static::saved(function (Presensi $presensi) {
            if ($presensi->wasChanged('is_pulang_awal') && $presensi->is_pulang_awal) {
                try {
                    app(PulangAwalNotifier::class)->notify($presensi);
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        });
    }

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
        'is_pulang_awal' => 'boolean',
        'pulang_awal_diizinkan' => 'boolean',
        'menit_pulang_awal' => 'integer',
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

    /**
     * Penanda pegawai benar-benar melakukan absen pulang.
     *
     * Jam keluar hasil pre-fill dari jadwal (unit tanpa `wajib_pulang_mengajar`
     * atau pegawai non-tetap/honorer) tidak boleh ditampilkan seolah pegawai
     * sudah pulang, jadi baris ber-jadwal dianggap bukan absen pulang bila jam
     * keluarnya persis sama dengan jam selesai jadwalnya TANPA koordinat keluar
     * (absen keluar sungguhan selalu mengisi `latitude_keluar`). Koreksi jam
     * keluar lewat pengajuan koreksi tetap dihitung selama waktunya berbeda
     * dari jadwal.
     */
    public function getKeluarAktualAttribute(): bool
    {
        if ($this->jam_keluar === null) {
            return false;
        }

        if ($this->latitude_keluar !== null || $this->jadwal_id === null) {
            return true;
        }

        $jamSelesai = $this->jadwal?->jam_selesai;

        return $jamSelesai === null
            || substr((string) $this->jam_keluar, 0, 5) !== substr((string) $jamSelesai, 0, 5);
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

    public function unitLokasiMasuk(): BelongsTo
    {
        return $this->belongsTo(UnitLokasi::class, 'unit_lokasi_masuk_id');
    }

    public function unitLokasiKeluar(): BelongsTo
    {
        return $this->belongsTo(UnitLokasi::class, 'unit_lokasi_keluar_id');
    }

    public function scopeTugasLuar($query)
    {
        return $query->where('is_tugas_luar', true);
    }
}
