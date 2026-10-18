<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengajuanPulangCepat extends Model
{
    use HasFactory;

    public const ALASANS = ['sakit', 'urusan_keluarga', 'keperluan_pribadi', 'dinas_luar', 'lainnya'];

    protected $fillable = [
        'pegawai_id',
        'tanggal',
        'jam_diminta',
        'alasan',
        'alasan_detail',
        'approver_id',
        'nomor',
    ];

    protected $casts = [
        'tanggal' => 'date:Y-m-d',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    /** Izin pulang cepat disetujui untuk pegawai pada tanggal tertentu (dasar buka gate). */
    public static function disetujuiPada(int $pegawaiId, Carbon|string $tanggal): ?self
    {
        $date = $tanggal instanceof Carbon ? $tanggal->toDateString() : (string) $tanggal;

        return static::where('pegawai_id', $pegawaiId)
            ->where('status', 'disetujui')
            ->whereDate('tanggal', $date)
            ->first();
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
