<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Jadwal extends Model
{
    /** Prefix cache jadwal mobile; naikkan versi saat isi cache berubah bentuk. */
    public const CACHE_PREFIX = 'mobile.jadwal.v2.';

    protected $table = 'jadwal';

    protected $fillable = [
        'pegawai_id',
        'unit_sekolah_id',
        'unit_lokasi_id',
        'pekan_ujian_id',
        'kelas_label',
        'pegawai_mapel_id',
        'hari',
        'jam_mulai',
        'jam_selesai',
        'jenis_jadwal',
        'tahun_ajaran',
        'semester',
    ];

    protected $appends = ['mata_pelajaran'];

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }

    public function unitSekolah(): BelongsTo
    {
        return $this->belongsTo(UnitSekolah::class);
    }

    public function unitLokasi(): BelongsTo
    {
        return $this->belongsTo(UnitLokasi::class);
    }

    public function pekanUjian(): BelongsTo
    {
        return $this->belongsTo(PekanUjian::class);
    }

    /**
     * Baris yang berlaku pada tanggal tertentu.
     * Pengawas hanya aktif di rentang periodenya; baris mengajar dorman
     * selama unit punya pekan ujian yang mencakup tanggal itu.
     */
    public function scopeEfektifPada(Builder $query, Carbon|string $tanggal): Builder
    {
        $date = Carbon::parse($tanggal)->toDateString();

        return $query->where(function (Builder $q) use ($date) {
            $q->where(function (Builder $q2) use ($date) {
                $q2->where('jenis_jadwal', 'pengawas_ujian')
                    ->whereHas('pekanUjian', fn (Builder $p) => $p
                        ->whereDate('tanggal_mulai', '<=', $date)
                        ->whereDate('tanggal_selesai', '>=', $date));
            })->orWhere(function (Builder $q2) use ($date) {
                $q2->where('jenis_jadwal', '!=', 'pengawas_ujian')
                    ->where(function ($q3) use ($date) {
                        $q3->where('jenis_jadwal', '!=', 'mengajar')
                            ->orWhereNotExists(function ($sub) use ($date) {
                                $sub->selectRaw('1')
                                    ->from('pekan_ujian')
                                    ->whereColumn('pekan_ujian.unit_sekolah_id', 'jadwal.unit_sekolah_id')
                                    ->whereDate('pekan_ujian.tanggal_mulai', '<=', $date)
                                    ->whereDate('pekan_ujian.tanggal_selesai', '>=', $date);
                            });
                    });
            });
        });
    }

    public function pegawaiMapel(): BelongsTo
    {
        return $this->belongsTo(PegawaiMapel::class);
    }

    public function getMataPelajaranAttribute(): ?MataPelajaran
    {
        return $this->pegawaiMapel?->mataPelajaran;
    }
}
