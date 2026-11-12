<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PekanUjian extends Model
{
    protected $table = 'pekan_ujian';

    protected $fillable = [
        'unit_sekolah_id',
        'nama',
        'tanggal_mulai',
        'tanggal_selesai',
        'keterangan',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
    ];

    public function unitSekolah(): BelongsTo
    {
        return $this->belongsTo(UnitSekolah::class);
    }

    public function jadwals(): HasMany
    {
        return $this->hasMany(Jadwal::class);
    }

    public function scopeForUnit(Builder $query, ?int $unitId): Builder
    {
        return $unitId ? $query->where('unit_sekolah_id', $unitId) : $query;
    }

    /** Periode yang mencakup tanggal tertentu untuk satu unit. */
    public static function aktifPada(int $unitId, Carbon|string $tanggal): ?self
    {
        $date = Carbon::parse($tanggal)->toDateString();

        return static::query()
            ->where('unit_sekolah_id', $unitId)
            ->whereDate('tanggal_mulai', '<=', $date)
            ->whereDate('tanggal_selesai', '>=', $date)
            ->first();
    }

    /**
     * Peta unit_id => periode aktif pada tanggal tertentu.
     * Dipakai untuk menonaktifkan baris mengajar per unit tanpa query per baris.
     *
     * @param  array<int>  $unitIds
     * @return array<int, self>
     */
    public static function mapAktifPada(Carbon|string $tanggal, array $unitIds): array
    {
        if ($unitIds === []) {
            return [];
        }

        $date = Carbon::parse($tanggal)->toDateString();

        return static::query()
            ->whereIn('unit_sekolah_id', array_values(array_unique($unitIds)))
            ->whereDate('tanggal_mulai', '<=', $date)
            ->whereDate('tanggal_selesai', '>=', $date)
            ->get()
            ->keyBy('unit_sekolah_id')
            ->all();
    }

    /** Status terhadap hari ini: akan_datang | aktif | selesai. */
    public function statusPada(Carbon|string|null $tanggal = null): string
    {
        $date = Carbon::parse($tanggal ?? Carbon::today())->startOfDay();
        $mulai = $this->tanggal_mulai->copy()->startOfDay();
        $selesai = $this->tanggal_selesai->copy()->endOfDay();

        if ($date->lt($mulai)) {
            return 'akan_datang';
        }
        if ($date->gt($selesai)) {
            return 'selesai';
        }

        return 'aktif';
    }

    /**
     * Periode lain di unit yang sama yang rentangnya overlap.
     * Satu unit hanya boleh punya satu pekan ujian pada satu waktu.
     */
    public static function bentrokRentang(int $unitId, string $mulai, string $selesai, ?int $kecualiId = null): ?self
    {
        return static::query()
            ->where('unit_sekolah_id', $unitId)
            ->when($kecualiId, fn (Builder $q) => $q->where('id', '!=', $kecualiId))
            ->whereDate('tanggal_mulai', '<=', $selesai)
            ->whereDate('tanggal_selesai', '>=', $mulai)
            ->first();
    }

    /**
     * Cache jadwal mobile berumur 15 menit. Perubahan periode harus
     * membuang cache pegawai unit agar jadwal mengajar langsung dorman
     * dan jadwal pengawas langsung tampil.
     */
    public function forgetJadwalCache(): void
    {
        $pegawaiIds = DB::table('pegawai_unit')
            ->where('unit_sekolah_id', $this->unit_sekolah_id)
            ->pluck('pegawai_id')
            ->unique();

        foreach ($pegawaiIds as $pegawaiId) {
            Cache::forget('mobile.jadwal.'.$pegawaiId);
            foreach (['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'] as $hari) {
                Cache::forget('mobile.jadwal.'.$pegawaiId.'.'.$hari);
            }
        }
    }
}
