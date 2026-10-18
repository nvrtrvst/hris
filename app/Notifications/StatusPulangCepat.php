<?php

namespace App\Notifications;

use App\Models\PengajuanPulangCepat;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;

// Sinkron (tanpa ShouldQueue): deployment tidak menjalankan queue worker.
class StatusPulangCepat extends Notification
{
    public function __construct(public PengajuanPulangCepat $pengajuan, public string $statusBaru, public ?string $alasanPenolakan = null) {}

    public function via($notifiable): array
    {
        return ['database', 'webpush', 'mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $p = $this->pengajuan;
        $disetujui = $this->statusBaru === 'disetujui';

        $mail = (new MailMessage)
            ->subject($disetujui ? 'Izin Pulang Cepat Disetujui' : 'Izin Pulang Cepat Ditolak')
            ->greeting('Yth. '.($notifiable->name ?? 'Pegawai').',')
            ->line("Pengajuan izin pulang cepat Anda tanggal {$p->tanggal?->format('d M Y')} telah ".($disetujui ? 'disetujui' : 'ditolak').'.');

        if ($disetujui) {
            $mail->line('Anda dapat presensi keluar lebih awal pada tanggal tersebut.');
        } elseif ($this->alasanPenolakan) {
            $mail->line("Alasan penolakan: {$this->alasanPenolakan}");
        }

        return $mail->salutation('Hormat kami, Tim HR Yayasan');
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        $p = $this->pengajuan;
        $disetujui = $this->statusBaru === 'disetujui';

        return (new WebPushMessage)
            ->title($disetujui ? 'Izin Pulang Cepat Disetujui ✅' : 'Izin Pulang Cepat Ditolak ❌')
            ->body($disetujui
                ? "Izin pulang cepat {$p->tanggal?->format('d M')} disetujui."
                : 'Izin pulang cepat ditolak.'.($this->alasanPenolakan ? " Alasan: {$this->alasanPenolakan}" : ''))
            ->badge(asset('/icons/icon-192.png'))
            ->icon(asset('/icons/icon-192.png'));
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'status_pulang_cepat',
            'pengajuan_id' => $this->pengajuan->id,
            'status' => $this->statusBaru,
            'alasan_penolakan' => $this->alasanPenolakan,
            'tanggal' => $this->pengajuan->tanggal?->format('Y-m-d'),
            'nomor' => $this->pengajuan->nomor,
        ];
    }
}
