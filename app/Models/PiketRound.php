<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PiketRound extends Model
{
    protected $table = 'piket_round';

    protected $fillable = ['sesi_id', 'area_id', 'petugas_id', 'putaran', 'jendela_mulai', 'jendela_selesai', 'selesai_at', 'status', 'alasan'];

    protected $casts = ['selesai_at' => 'datetime', 'putaran' => 'int'];

    public function area()
    {
        return $this->belongsTo(PiketArea::class, 'area_id');
    }
}
