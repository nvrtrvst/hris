<?php

namespace App\Notifications;

use App\Models\Presensi;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;

// Sinkron (tanpa ShouldQueue): deployment tidak menjalankan queue worker,
// jadi notifikasi dikirim langsung agar tidak menumpuk di tabel jobs.
class StatusLembur extends Notification
{
    public function __construct(public Presensi $presensi, public string $statusBaru) {}

    public function via($notifiable): array
    {
        return ['database', 'webpush', 'mail'];
    }

    public function durasiJam(): ?float
    {
        $p = $this->presensi;

        if (! $p->jam_masuk || ! $p->jam_keluar) {
            return null;
        }

        return round((strtotime($p->jam_keluar) - strtotime($p->jam_masuk)) / 3600, 2);
    }

    public function toMail($notifiable): MailMessage
    {
        $disetujui = $this->statusBaru === 'disetujui';
        $durasi = $this->durasiJam();
        $durasiText = $durasi !== null ? " ({$durasi} jam)" : '';

        return (new MailMessage)
            ->subject($disetujui ? 'Lembur Disetujui' : 'Lembur Ditolak')
            ->greeting('Yth. '.($notifiable->name ?? 'Pegawai').',')
            ->line("Pengajuan lembur Anda pada {$this->presensi->tanggal->format('d M Y')}{$durasiText} telah ".($disetujui ? 'disetujui' : 'ditolak').'.')
            ->salutation('Hormat kami, Tim HR Yayasan');
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        $disetujui = $this->statusBaru === 'disetujui';
        $body = $disetujui
            ? 'Pengajuan lembur Anda disetujui.'
            : 'Pengajuan lembur Anda ditolak.';

        return (new WebPushMessage)
            ->title($disetujui ? 'Lembur Disetujui ✅' : 'Lembur Ditolak ❌')
            ->body($body)
            ->badge(asset('/icons/icon-192.png'))
            ->icon(asset('/icons/icon-192.png'));
    }

    public function toDatabase($notifiable): array
    {
        $durasi = $this->durasiJam();
        $durasiText = $durasi !== null ? " ({$durasi} jam)" : '';
        $disetujui = $this->statusBaru === 'disetujui';

        return [
            'type' => 'status_lembur',
            'presensi_id' => $this->presensi->id,
            'status' => $this->statusBaru,
            'tanggal' => $this->presensi->tanggal?->format('Y-m-d'),
            'durasi_jam' => $damai,
            'message' => ($disetujui ? 'Lembur Anda' : 'Pengajuan lembur Anda')." pada {$this->presensi->tanggal?->translatedFormat('d M Y')}{$durasiText} ".($disetujui ? 'disetujui.' : 'ditolak.'),
        ];
    }
}
