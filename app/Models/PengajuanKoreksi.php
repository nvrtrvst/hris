<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PengajuanKoreksi extends Model
{
    use HasFactory;

    /** Kuota koreksi disetujui per pegawai per bulan. Lewat kuota: boleh + wajib penjelasan. */
    public const KUOTA_BULANAN = 3;

    public const ALASANS = ['lupa_presensi', 'hp_rusak', 'dinas_luar', 'rapat', 'lainnya'];

    protected $fillable = [
        'pegawai_id',
        'presensi_id',
        'tanggal',
        'nilai_baru',
        'alasan',
        'alasan_detail',
        'penjelasan_khusus',
        'approver_id',
        'nomor',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    /** Jumlah koreksi berstatus disetujui pada bulan berjalan (dasar kuota). */
    public static function kuotaTerpakai(int $pegawaiId): int
    {
        return static::where('pegawai_id', $pegawaiId)
            ->where('status', 'disetujui')
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();
    }

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class);
    }

    public function presensi()
    {
        return $this->belongsTo(Presensi::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function rejectedByUser()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }
}
