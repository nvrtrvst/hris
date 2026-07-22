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
        'wajib_kantor',
    ];

    protected $appends = ['sisa_cuti', 'cuti_terpakai', 'foto_url', 'nik_masked'];

    /**
     * Field sensitif yang TIDAK boleh diserialize ke FE / API.
     * NIK plaintext diakses via endpoint khusus `pegawai.nik-asli`
     * dengan Gate `view_sensitive_data`.
     */
    protected $hidden = ['nik', 'nik_hash'];

    protected $casts = [
        'tanggal_lahir' => 'date',
        'tanggal_mulai_kerja' => 'date',
        'tanggal_akhir_kontrak' => 'date',
        'nik' => 'encrypted',
        'no_rekening' => 'encrypted',
        'nama_bank' => 'encrypted',
        'npwp' => 'encrypted',
        'no_bpjs_kesehatan' => 'encrypted',
        'no_bpjs_ketenagakerjaan' => 'encrypted',
        'wajib_kantor' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Sync nik_hash setiap NIK di-set / di-update.
        // Pakai saving hook (sebelum persist) agar hash selalu konsisten
        // dengan ciphertext di DB.
        //
        // ponytail: skip hash sync untuk existing-rows mass-loads (seeding &
        // refresh DB); hash akan di-set via command `pegawai:hash-nik`
        // setelah data legacy di-migrate ke format ciphertext.
        static::saving(function (Pegawai $pegawai) {
            if (! $pegawai->isDirty('nik')) {
                return;
            }

            $raw = $pegawai->getAttributes()['nik'] ?? null;

            // Deteksi legacy plaintext (16-digit NIK polos) vs ciphertext Laravel.
            // Ciphertext selalu starts with `eyJ` (base64 JSON {"iv":...}).
            // Plaintext legacy berbentuk digit-only.
            $isLegacyPlain = is_string($raw) && (bool) preg_match('/^\d{8,}$/', $raw);
            $isCipher = is_string($raw) && str_starts_with($raw, 'eyJ');

            // Mass-load / mass-assign NIK legacy (skip — biar idempotent, gak
            // double-decrypt yg trigger "payload invalid" di seeder chains).
            if ($isLegacyPlain) {
                return;
            }

            // Kalau bukan ciphertext beneran juga bukan legacy → skip (safety).
            if (! $isCipher && ! $isLegacyPlain) {
                return;
            }

            try {
                $decrypted = \Illuminate\Support\Facades\Crypt::decryptString($raw);
            } catch (\Throwable) {
                // Decrypt gagal (legacy plaintext yang ke-cast encrypted, atau
                // data korup). Jangan crash — biarkan nilai apa adanya dan
                // jangan set hash. Command backfill yang akan perbaiki.
                return;
            }

            $trimmed = trim($decrypted);
            $pegawai->nik = $trimmed !== '' ? \Illuminate\Support\Facades\Crypt::encryptString($trimmed) : null;
            $pegawai->nik_hash = $trimmed !== '' ? hash('sha256', $trimmed) : null;
        });
    }

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

    /**
     * NIK tersensor: 4 awal + 8 bintang + 4 akhir.
     * Aman di-share ke FE (tidak bocor plaintext).
     * Jika NIK kosong atau < 8 char → tampilkan 16 bintang.
     */
    public function getNikMaskedAttribute(): string
    {
        $plain = (string) ($this->nik ?? '');

        if ($plain === '') {
            return str_repeat('*', 16);
        }

        $len = mb_strlen($plain);
        if ($len < 8) {
            return str_repeat('*', max(8, $len));
        }

        $prefix = mb_substr($plain, 0, 4);
        $suffix = mb_substr($plain, -4);

        return $prefix . str_repeat('*', 8) . $suffix;
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
