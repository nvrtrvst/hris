<?php

namespace App\Notifications;

use App\Models\PengajuanIzin;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class IzinBaru extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public PengajuanIzin $pengajuan) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        $p = $this->pengajuan;
        $pegawai = $p->pegawai;

        return [
            'type' => 'izin_baru',
            'pengajuan_id' => $p->id,
            'pegawai_id' => $pegawai?->id,
            'pegawai_nama' => $pegawai?->nama_lengkap ?? '(tanpa nama)',
            'jenis_izin' => $p->jenis_izin,
            'tanggal_mulai' => $p->tanggal_mulai?->format('Y-m-d'),
            'tanggal_selesai' => $p->tanggal_selesai?->format('Y-m-d'),
            'alasan' => $p->alasan,
            'created_at' => $p->created_at?->toISOString(),
        ];
    }
}
