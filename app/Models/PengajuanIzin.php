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
        'catatan_approval',
        'dihitung_hadir_kcd',
        'dihitung_setengah',
        'dengan_surat_dokter',
        'sumber',
        'created_by',
    ];

    protected $guarded = [
        'status',
        'approval_stage',
        'approver_l1_id',
        'approver_l2_id',
        'approved_at_l1',
        'approved_at_l2',
        'rejected_by',
        'tugas_terverifikasi',
        'tugas_diverifikasi_at',
        'tugas_diverifikasi_oleh',
    ];

    protected $casts = [
        // Cast date PENTING: notifikasi (IzinBaru/StatusIzin) memanggil
        // ->format() pada tanggal ini — tanpa cast, tanggal berupa string dan
        // notifikasi gagal tersimpan (exception ditelan NotificationHelper).
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'approved_at_l1' => 'datetime',
        'approved_at_l2' => 'datetime',
        'due_at' => 'datetime',
        'escalated_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'dihitung_setengah' => 'boolean',
        'dengan_surat_dokter' => 'boolean',
        'tugas_terverifikasi' => 'boolean',
        'tugas_diverifikasi_at' => 'datetime',
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

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approverL1()
    {
        return $this->belongsTo(User::class, 'approver_l1_id');
    }

    public function approverL2()
    {
        return $this->belongsTo(User::class, 'approver_l2_id');
    }

    public function tugasDiverifikasiOleh()
    {
        return $this->belongsTo(User::class, 'tugas_diverifikasi_oleh');
    }

    public function rejectedByUser()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function comments()
    {
        return $this->hasMany(PengajuanIzinComment::class)->oldest();
    }
}
