<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Observers\PegawaiObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

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
    ];

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
}
