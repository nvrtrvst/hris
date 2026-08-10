<?php

namespace App\Notifications;

use App\Models\PengajuanIzin;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;

// Sinkron (tanpa ShouldQueue): deployment tidak menjalankan queue worker,
// jadi notifikasi dikirim langsung agar tidak menumpuk di tabel jobs.
class IzinBaru extends Notification
{
    public function __construct(public PengajuanIzin $pengajuan) {}

    public function via($notifiable): array
    {
        return ['database', 'webpush', 'mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $p = $this->pengajuan;
        $namaPegawai = $p->pegawai?->nama_lengkap ?? 'Pegawai';
        $label = strtoupper($p->jenis_izin);

        return (new MailMessage)
            ->subject("Pengajuan {$label} Baru — Perlu Persetujuan")
            ->greeting('Yth. '.($notifiable->name ?? 'Approver').',')
            ->line("{$namaPegawai} mengajukan {$label}.")
            ->line("Periode: {$p->tanggal_mulai?->format('d M Y')} s.d. {$p->tanggal_selesai?->format('d M Y')}")
            ->line("Alasan: {$p->alasan}")
            ->action('Tinjau Pengajuan', route('pengajuan-izin.index'))
            ->salutation('Hormat kami, Sistem HRIS');
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        $p = $this->pengajuan;
        $namaPegawai = $p->pegawai?->nama_lengkap ?? 'Pegawai';
        $label = strtoupper($p->jenis_izin);

        return (new WebPushMessage)
            ->title("Pengajuan {$label} Baru")
            ->body("{$namaPegawai} mengajukan {$label} ({$p->tanggal_mulai?->format('d M')} - {$p->tanggal_selesai?->format('d M')}).".($p->alasan ? " Alasan: {$p->alasan}" : ''))
            ->badge(asset('/icons/icon-192.png'))
            ->icon(asset('/icons/icon-192.png'));
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
