<?php

namespace App\Models;

use App\Helpers\FileHelper;
use App\Observers\PegawaiObserver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy([PegawaiObserver::class])]
class Pegawai extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'pegawai';

    protected $fillable = [
        'user_id',
        'nik',
        'nip',
        'nama_lengkap',
        'foto',
        'tempat_lahir',
        'tanggal_lahir',
        'jenis_kelamin',
        'agama',
        'status_pernikahan',
        'jumlah_tanggungan',
        'alamat_ktp',
        'alamat_domisili',
        'no_hp',
        'no_hp_darurat',
        'email',
        'status_kepegawaian',
        'tanggal_mulai_kerja',
        'tanggal_akhir_kontrak',
        'atasan_langsung_id',
        'status_aktif',
        'alasan_nonaktif',
        'pendidikan_terakhir',
        'nuptk',
        'no_rekening',
        'nama_bank',
        'npwp',
        'no_bpjs_kesehatan',
        'no_bpjs_ketenagakerjaan',
        'jatah_cuti_tahunan',
    ];

    protected $appends = ['sisa_cuti', 'cuti_terpakai', 'foto_url'];

    protected $casts = [
        'tanggal_lahir' => 'date',
        'tanggal_mulai_kerja' => 'date',
        'tanggal_akhir_kontrak' => 'date',
        'no_rekening' => 'encrypted',
        'nama_bank' => 'encrypted',
        'npwp' => 'encrypted',
        'no_bpjs_kesehatan' => 'encrypted',
        'no_bpjs_ketenagakerjaan' => 'encrypted',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function atasanLangsung(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'atasan_langsung_id');
    }

    public function bawahan(): HasMany
    {
        return $this->hasMany(Pegawai::class, 'atasan_langsung_id');
    }

    public function units(): BelongsToMany
    {
        return $this->belongsToMany(UnitSekolah::class, 'pegawai_unit')
            ->withPivot(['jabatan_id', 'is_primary'])
            ->withTimestamps();
    }

    public function jabatans(): BelongsToMany
    {
        return $this->belongsToMany(Jabatan::class, 'pegawai_unit')
            ->withPivot('unit_sekolah_id', 'is_primary')
            ->withTimestamps();
    }

    public function komponenGaji(): BelongsToMany
    {
        return $this->belongsToMany(KomponenGaji::class, 'pegawai_komponen_gaji')
            ->withPivot('nominal')
            ->withTimestamps();
    }

    public function mapels(): BelongsToMany
    {
        return $this->belongsToMany(MataPelajaran::class, 'pegawai_mapel')
            ->withPivot('unit_sekolah_id')
            ->withTimestamps();
    }

    public function dokumen(): HasMany
    {
        return $this->hasMany(PegawaiDokumen::class);
    }

    public function pengajuanIzins()
    {
        return $this->hasMany(PengajuanIzin::class);
    }

    public function riwayat(): HasMany
    {
        return $this->hasMany(PegawaiRiwayat::class);
    }

    public function getCutiTerpakaiAttribute()
    {
        $year = (int) date('Y');
        $collection = $this->relationLoaded('pengajuanIzins')
            ? $this->pengajuanIzins
            : $this->pengajuanIzins()->get();

        return $collection
            ->where('jenis_izin', 'cuti')
            ->where('status', 'disetujui')
            ->filter(function ($izin) use ($year) {
                return Carbon::parse($izin->tanggal_mulai)->year === $year;
            })
            ->sum(function ($izin) {
                return Carbon::parse($izin->tanggal_mulai)->diffInDays(Carbon::parse($izin->tanggal_selesai)) + 1;
            });
    }

    public function getSisaCutiAttribute()
    {
        $jatah = $this->jatah_cuti_tahunan ?? 12;

        return max(0, $jatah - $this->cuti_terpakai);
    }

    public function getFotoUrlAttribute(): ?string
    {
        return FileHelper::fotoUrl($this->foto);
    }

    public function jadwals(): HasMany
    {
        return $this->hasMany(Jadwal::class, 'pegawai_id', 'id');
    }

    /**
     * Scope: batasi ke pegawai yang termasuk unit tertentu.
     * Pegawai TIDAK punya kolom unit_sekolah_id — relasi via pegawai_unit.
     */
    public function scopeForUnit($query, $unitId)
    {
        return $query->whereHas('units', fn ($q) => $q->where('unit_sekolah.id', $unitId));
    }

    /**
     * Cek apakah pegawai termasuk unit tertentu.
     */
    public function belongsToUnit($unitId): bool
    {
        return $this->units()->where('unit_sekolah.id', $unitId)->exists();
    }
}
