<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditPresensi extends Model
{
    protected $table = 'audit_presensi';

    protected $fillable = [
        'presensi_id',
        'user_id',
        'aksi',
        'field',
        'nilai_lama',
        'nilai_baru',
        'keterangan',
    ];

    public function presensi()
    {
        return $this->belongsTo(Presensi::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function log($presensiId, $aksi, $field = null, $nilaiLama = null, $nilaiBaru = null, $keterangan = null): void
    {
        self::create([
            'presensi_id' => $presensiId,
            'user_id' => auth()->id(),
            'aksi' => $aksi,
            'field' => $field,
            'nilai_lama' => $nilaiLama,
            'nilai_baru' => $nilaiBaru,
            'keterangan' => $keterangan,
        ]);
    }
}
