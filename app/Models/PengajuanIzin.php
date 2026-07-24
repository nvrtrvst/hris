<?php

namespace App\Models;

use App\Helpers\FileHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PengajuanIzin extends Model
{
    use HasFactory;

    protected $fillable = [
        'pegawai_id',
        'jenis_izin',
        'tanggal_mulai',
        'tanggal_selesai',
        'alasan',
        'bukti_foto',
        'status',
        'alasan_penolakan',
        'approval_stage',
        'approver_l1_id',
        'approver_l2_id',
        'approved_at_l1',
        'approved_at_l2',
        'rejected_by',
    ];

    protected $casts = [
        'approved_at_l1' => 'datetime',
        'approved_at_l2' => 'datetime',
    ];

    protected $appends = ['bukti_foto_url'];

    public function getBuktiFotoUrlAttribute(): ?string
    {
        return FileHelper::fotoUrl($this->bukti_foto);
    }

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class);
    }
}
