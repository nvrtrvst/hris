<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PiketSesi extends Model
{
    protected $table = 'piket_sesi';

    protected $fillable = ['unit_sekolah_id', 'tanggal', 'jam_mulai', 'jam_selesai', 'status', 'assignment_version', 'ditutup_oleh', 'ditutup_at'];

    protected $casts = ['tanggal' => 'date:Y-m-d', 'ditutup_at' => 'datetime', 'assignment_version' => 'int'];

    public function unitSekolah()
    {
        return $this->belongsTo(UnitSekolah::class);
    }

    public function petugas()
    {
        return $this->hasMany(PiketPetugas::class, 'sesi_id');
    }

    public function rounds()
    {
        return $this->hasMany(PiketRound::class, 'sesi_id');
    }

    public function posLogs()
    {
        return $this->hasMany(PiketPosLog::class, 'sesi_id');
    }
}
