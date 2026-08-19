<?php

namespace App\Models;

use App\Helpers\FileHelper;
use App\Observers\PegawaiObserver;
use Carbon\Carbon;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

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
        'pendidikan_jurusan',
        'no_rekening',
        'nama_bank',
        'npwp',
        'no_bpjs_kesehatan',
        'no_bpjs_ketenagakerjaan',
        'jatah_cuti_tahunan',
        'wajib_kantor',
        'created_by',
    ];

    // P2: sisa_cuti/cuti_terpakai TIDAK di-appends global — accessor-nya query DB
    // (pengajuanIzins). Kalau auto-append, setiap serialisasi (termasuk auth.user.pegawai
    // di HandleInertiaRequests) memicu query per request. Append eksplisit di controller
    // yang butuh, dengan eager-load pengajuanIzins.
    protected $appends = ['foto_url', 'nik_masked'];

    /**
     * Muat data cuti + append accessor sisa_cuti/cuti_terpakai.
     * Panggil dari controller yang menampilkan sisa cuti di FE.
     *
     * @param  \Closure|null  $scope  Scope kolom/where utk pengajuanIzins
     *                                (mis. hanya kolom yg dipakai accessor).
     */
    public function loadCutiInfo(?\Closure $scope = null): static
    {
        $this->load(['pengajuanIzins' => $scope ?: fn ($q) => $q]);
        $this->append(['sisa_cuti', 'cuti_terpakai']);

        return $this;
    }

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
        // refresh DB); Seeder pre-compute nik_hash + cipher via firstOrCreate
        // untuk bypass double-cast hook miss.
        static::saving(function (Pegawai $pegawai) {
            if (! $pegawai->isDirty('nik')) {
                return;
            }

            // Baca raw attribute (sebelum cast). Di saving hook:
            //   - setter Eloquent menyimpan plaintext asli di sini (bukan ciphertext)
            //   - cast `encrypted` baru jalan saat persist ke DB
            // jadi raw = input user, bisa digit-only legacy atau ciphertext Laravel.
            $raw = $pegawai->getAttributes()['nik'] ?? null;

            if (! is_string($raw) || $raw === '') {
                $pegawai->nik_hash = null;

                return;
            }

            $plaintext = null;

            // Branch A: plaintext digit-only (legacy seeders / mass-assign dari controller).
            if (preg_match('/^\d{8,}$/', $raw) === 1) {
                $plaintext = trim($raw);
            } elseif (str_starts_with($raw, 'eyJ')) {
                // Branch B: ciphertext Laravel (`{"iv":"..."}` base64). Decrypt normal.
                try {
                    $plaintext = trim(Crypt::decryptString($raw));
                } catch (\Throwable) {
                    // Gagal decrypt (data korup). Skip — backfill command akan perbaiki.
                    return;
                }
            } else {
                // Bukan digit-only, bukan ciphertext — bentuk tak dikenal. Skip aman.
                return;
            }

            // Tetapkan hash + ciphertext dari plaintext hasil normalisasi.
            // Re-assign ciphertext agar konsisten walau cast sudah handle.
            $pegawai->nik = $plaintext !== ''
                ? Crypt::encryptString($plaintext)
                : null;
            $pegawai->nik_hash = self::nikHash($plaintext);
        });
    }

    /**
     * Normalisasi NIK untuk lookup equality: trim + SHA-256.
     * Satu-satunya sumber kebenaran — semua search/import/seed wajib pakai ini
     * agar tidak drift dengan saving hook di atas yang menulis nik_hash.
     */
    /**
     * NIK plaintext aman. DB bisa double-encrypted (saving hook + cast encrypted),
     * jadi decrypt pertama bisa menghasilkan ciphertext dalam (JWT-shaped) —
     * peel satu lapis ekstra. Read-only; dipakai endpoint nik-asli, form edit,
     * dan export. Jangan pernah menulis kembali ke DB dari sini.
     */
    public function getNikPlaintext(): ?string
    {
        $raw = $this->getRawOriginal('nik');
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        try {
            $plain = Crypt::decryptString($raw);
        } catch (DecryptException) {
            return null;
        }

        if (is_string($plain) && preg_match('/^eyJ[A-Za-z0-9+\/=]+$/', $plain)) {
            try {
                $plain = Crypt::decryptString($plain);
            } catch (DecryptException) {
                return null;
            }
        }

        return $plain !== '' ? $plain : null;
    }

    public static function nikHash(string $nik): ?string
    {
        $plaintext = trim($nik);

        return $plaintext !== '' ? hash('sha256', $plaintext) : null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function atasanLangsung(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'atasan_langsung_id');
    }

    /**
     * Jabatan dari penugasan PRIMARY (atau penugasan pertama bila tidak ada
     * yang ditandai primary). Asumsikan relasi `units` sudah eager-load dengan
     * pivot jabatan_id/is_primary.
     */
    public function jabatanPrimer(): ?Jabatan
    {
        $unit = $this->units->first(fn ($u) => ! empty($u->pivot->is_primary))
            ?? $this->units->first();

        return $unit?->pivot?->jabatan_id
            ? Jabatan::find($unit->pivot->jabatan_id)
            : null;
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
                // Hitung hanya hari kerja (Senin–Jumat)
                $start = Carbon::parse($izin->tanggal_mulai);
                $end = Carbon::parse($izin->tanggal_selesai);
                $count = 0;
                $current = $start->copy();
                while ($current->lte($end)) {
                    if ($current->isWeekday()) {
                        $count++;
                    }
                    $current->addDay();
                }
                return $count;
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

        return $prefix.str_repeat('*', 8).$suffix;
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
     * Scope: pendidik = punya minimal satu jabatan guru (is_guru = true).
     * Konsisten dengan filter jenis Dapodik di Presensi/Pegawai/Jadwal.
     */
    public function scopeGuru($query)
    {
        return $query->whereHas('jabatans', fn ($q) => $q->where('is_guru', true));
    }

    /**
     * Scope: tenaga kependidikan = TIDAK punya jabatan guru sama sekali
     * (TU, pustakawan, laboran, OB, satpam, dll).
     */
    public function scopeNonGuru($query)
    {
        return $query->whereDoesntHave('jabatans', fn ($q) => $q->where('is_guru', true));
    }

    /**
     * Label jenis pegawai (Dapodik): 'Pendidik' jika punya minimal satu
     * jabatan guru (is_guru = true), selain itu 'Tenaga Kependidikan'.
     * Dipakai untuk kolom Jenis di laporan/export. Pastikan relasi
     * `jabatans` sudah di-eager-load sebelum dipanggil dalam loop (hindari N+1).
     */
    public function jenisPegawaiLabel(): string
    {
        return $this->jabatans->contains('is_guru', true)
            ? 'Pendidik'
            : 'Tenaga Kependidikan';
    }

    /**
     * Cek apakah pegawai termasuk unit tertentu.
     */
    public function belongsToUnit($unitId): bool
    {
        return $this->units()->where('unit_sekolah.id', $unitId)->exists();
    }

    public function isDataComplete(): bool
    {
        $required = [
            'nik', 'nama_lengkap', 'tempat_lahir', 'tanggal_lahir',
            'jenis_kelamin', 'agama', 'status_pernikahan', 'jumlah_tanggungan',
            'alamat_ktp', 'no_hp', 'status_kepegawaian', 'tanggal_mulai_kerja',
            'pendidikan_terakhir',
            'nama_bank', 'no_rekening',
        ];

        foreach ($required as $field) {
            $value = $this->{$field};
            if ($value === null || $value === '' || $value === []) {
                return false;
            }
        }

        return true;
    }
}
