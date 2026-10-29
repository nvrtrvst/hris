<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PiketSiswaTerlambat extends Model
{
    protected $table = 'piket_siswa_terlambat';

    protected $fillable = [
        'unit_sekolah_id',
        'piket_sesi_id',
        'piket_pos_id',
        'pegawai_id',
        'tanggal',
        'class_id',
        'class_label',
        'nis',
        'nama_snapshot',
        'jam_terlambat',
        'keterangan',
        'created_by',
    ];

    protected $casts = [
        'tanggal' => 'date:Y-m-d',
    ];

    public function unitSekolah()
    {
        return $this->belongsTo(UnitSekolah::class);
    }

    public function sesi()
    {
        return $this->belongsTo(PiketSesi::class, 'piket_sesi_id');
    }

    public function pos()
    {
        return $this->belongsTo(PiketPos::class, 'piket_pos_id');
    }

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class);
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
