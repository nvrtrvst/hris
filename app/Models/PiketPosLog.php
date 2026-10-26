<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PiketPosLog extends Model
{
    protected $table = 'piket_pos_log';

    protected $fillable = ['sesi_id', 'pos_id', 'petugas_id', 'done_at', 'nihil', 'jumlah', 'catatan', 'sumber', 'override_by', 'alasan_override'];

    protected $casts = ['done_at' => 'datetime', 'nihil' => 'boolean'];

    public function overrideBy()
    {
        return $this->belongsTo(User::class, 'override_by');
    }

    public function pos()
    {
        return $this->belongsTo(PiketPos::class, 'pos_id');
    }
}
