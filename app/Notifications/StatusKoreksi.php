<?php

namespace App\Notifications;

use App\Models\PengajuanKoreksi;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;

// Sinkron (tanpa ShouldQueue): deployment tidak menjalankan queue worker,
// jadi notifikasi dikirim langsung agar tidak menumpuk di tabel jobs.
class StatusKoreksi extends Notification
{
    public function __construct(public PengajuanKoreksi $koreksi, public string $statusBaru, public ?string $alasanPenolakan = null) {}

    public function via($notifiable): array
    {
        return ['database', 'webpush', 'mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $k = $this->koreksi;
        $disetujui = $this->statusBaru === 'disetujui';

        $mail = (new MailMessage)
            ->subject($disetujui ? 'Pengajuan Koreksi Presensi Disetujui' : 'Pengajuan Koreksi Presensi Ditolak')
            ->greeting('Yth. '.($notifiable->name ?? 'Pegawai').',')
            ->line("Pengajuan koreksi presensi Anda tanggal {$k->tanggal?->format('d M Y')} telah ".($disetujui ? 'disetujui' : 'ditolak').'.');

        if ($disetujui) {
            $mail->line("Jam keluar baru: {$k->nilai_baru} ({$k->nomor}).");
        } elseif ($this->alasanPenolakan) {
            $mail->line("Alasan penolakan: {$this->alasanPenolakan}");
        }

        return $mail->salutation('Hormat kami, Tim HR Yayasan');
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        $k = $this->koreksi;
        $disetujui = $this->statusBaru === 'disetujui';
        $body = $disetujui
            ? "Koreksi presensi {$k->tanggal?->format('d M')} disetujui. Jam keluar: {$k->nilai_baru}."
            : 'Koreksi presensi ditolak.'.($this->alasanPenolakan ? " Alasan: {$this->alasanPenolakan}" : '');

        return (new WebPushMessage)
            ->title($disetujui ? 'Koreksi Presensi Disetujui ✅' : 'Koreksi Presensi Ditolak ❌')
            ->body($body)
            ->badge(asset('/icons/icon-192.png'))
            ->icon(asset('/icons/icon-192.png'));
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'status_koreksi',
            'koreksi_id' => $this->koreksi->id,
            'status' => $this->statusBaru,
            'alasan_penolakan' => $this->alasanPenolakan,
            'tanggal' => $this->koreksi->tanggal?->format('Y-m-d'),
            'nilai_baru' => $this->koreksi->nilai_baru,
            'nomor' => $this->koreksi->nomor,
        ];
    }
}
