<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PengajuanIzinComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'pengajuan_izin_id',
        'user_id',
        'message',
    ];

    public function pengajuanIzin()
    {
        return $this->belongsTo(PengajuanIzin::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
