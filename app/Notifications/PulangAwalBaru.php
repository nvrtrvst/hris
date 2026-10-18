<?php

namespace App\Notifications;

use App\Models\Presensi;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;

// Sinkron (tanpa ShouldQueue): deployment tidak menjalankan queue worker.
class PulangAwalBaru extends Notification
{
    public function __construct(public Presensi $presensi, public int $menit) {}

    public function via($notifiable): array
    {
        return ['database', 'webpush', 'mail'];
    }

    private function labelWaktu(): string
    {
        return substr((string) $this->presensi->jam_keluar, 0, 5);
    }

    public function toMail($notifiable): MailMessage
    {
        $p = $this->presensi;
        $nama = $p->pegawai?->nama_lengkap ?? 'Pegawai';

        return (new MailMessage)
            ->subject('Pegawai Pulang Lebih Awal — Perlu Ditindaklanjuti')
            ->greeting('Yth. '.($notifiable->name ?? 'Atasan').',')
            ->line("{$nama} tercatat pulang lebih awal hari ini.")
            ->line("Tanggal: {$p->tanggal?->format('d M Y')} · Jam keluar: {$this->labelWaktu()}")
            ->line("Selisih dari jam pulang semestinya: ±{$this->menit} menit.")
            ->action('Lihat Presensi', route('presensi.index'))
            ->salutation('Hormat kami, Sistem HRIS');
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        $p = $this->presensi;

        return (new WebPushMessage)
            ->title('Pegawai Pulang Lebih Awal')
            ->body(($p->pegawai?->nama_lengkap ?? 'Pegawai')." pulang {$this->labelWaktu()} (±{$this->menit} menit lebih awal).")
            ->badge(asset('/icons/icon-192.png'))
            ->icon(asset('/icons/icon-192.png'));
    }

    public function toDatabase($notifiable): array
    {
        $p = $this->presensi;

        return [
            'type' => 'pulang_awal_baru',
            'presensi_id' => $p->id,
            'pegawai_id' => $p->pegawai_id,
            'pegawai_nama' => $p->pegawai?->nama_lengkap ?? '(tanpa nama)',
            'tanggal' => $p->tanggal?->format('Y-m-d'),
            'jam_keluar' => $p->jam_keluar,
            'menit_pulang_awal' => $this->menit,
        ];
    }
}
