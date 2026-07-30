<?php

namespace App\Notifications;

use App\Models\PengajuanIzin;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class StatusIzin extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public PengajuanIzin $pengajuan, public string $statusBaru, public ?string $alasanPenolakan = null) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'status_izin',
            'pengajuan_id' => $this->pengajuan->id,
            'status' => $this->statusBaru,
            'alasan_penolakan' => $this->alasanPenolakan,
            'jenis_izin' => $this->pengajuan->jenis_izin,
            'tanggal_mulai' => $this->pengajuan->tanggal_mulai?->format('Y-m-d'),
            'tanggal_selesai' => $this->pengajuan->tanggal_selesai?->format('Y-m-d'),
        ];
    }
}
