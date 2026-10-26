<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbsenSiswaRevisi extends Model
{
    protected $table = 'absen_siswa_revisi';

    protected $fillable = ['absen_siswa_sesi_id', 'user_id', 'peran', 'ringkasan', 'details', 'alasan'];

    protected $casts = ['ringkasan' => 'array', 'details' => 'array'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
