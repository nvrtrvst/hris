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
        'alasan_penolakan',
    ];

    protected $guarded = [
        'status',
        'approval_stage',
        'approver_l1_id',
        'approver_l2_id',
        'approved_at_l1',
        'approved_at_l2',
        'rejected_by',
    ];

    protected $casts = [
        // Cast date PENTING: notifikasi (IzinBaru/StatusIzin) memanggil
        // ->format() pada tanggal ini — tanpa cast, tanggal berupa string dan
        // notifikasi gagal tersimpan (exception ditelan NotificationHelper).
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
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
