<?php

namespace App\Notifications;

use App\Models\PengajuanIzin;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;

// Sinkron (tanpa ShouldQueue): deployment tidak menjalankan queue worker,
// jadi notifikasi dikirim langsung agar tidak menumpuk di tabel jobs.
class StatusIzin extends Notification
{
    public function __construct(public PengajuanIzin $pengajuan, public string $statusBaru, public ?string $alasanPenolakan = null) {}

    public function via($notifiable): array
    {
        return ['database', 'webpush', 'mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $p = $this->pengajuan;
        $label = strtoupper($p->jenis_izin);
        $disetujui = $this->statusBaru === 'disetujui';

        $mail = (new MailMessage)
            ->subject($disetujui ? "Pengajuan {$label} Disetujui" : "Pengajuan {$label} Ditolak")
            ->greeting('Yth. '.($notifiable->name ?? 'Pegawai').',')
            ->line("Pengajuan {$label} Anda dari {$p->tanggal_mulai?->format('d M Y')} s.d. {$p->tanggal_selesai?->format('d M Y')} telah ".($disetujui ? 'disetujui' : 'ditolak').'.');

        if (! $disetujui && $this->alasanPenolakan) {
            $mail->line("Alasan penolakan: {$this->alasanPenolakan}");
        }

        return $mail->salutation('Hormat kami, Tim HR Yayasan');
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        $label = strtoupper($this->pengajuan->jenis_izin);
        $disetujui = $this->statusBaru === 'disetujui';
        $body = $disetujui
            ? "Pengajuan {$label} Anda disetujui."
            : "Pengajuan {$label} Anda ditolak.".($this->alasanPenolakan ? " Alasan: {$this->alasanPenolakan}" : '');

        return (new WebPushMessage)
            ->title($disetujui ? 'Pengajuan Disetujui ✅' : 'Pengajuan Ditolak ❌')
            ->body($body)
            ->badge(asset('/icons/icon-192.png'))
            ->icon(asset('/icons/icon-192.png'));
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
