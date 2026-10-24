<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris kehadiran per siswa per sesi absen piket. Status mengikuti
 * standar presensi: hadir|telat|izin|sakit|alpa (default hadir — siswa yang
 * tidak dicentang berubah status tetap HADIR).
 */
class AbsenSiswaDetail extends Model
{
    public const STATUS_YA = ['hadir', 'telat', 'izin', 'sakit', 'alpa'];

    protected $table = 'absen_siswa_detail';

    protected $fillable = [
        'absen_siswa_sesi_id',
        'nis',
        'nama_snapshot',
        'status',
        'keterangan',
    ];

    public function sesi(): BelongsTo
    {
        return $this->belongsTo(AbsenSiswaSesi::class, 'absen_siswa_sesi_id');
    }
}
