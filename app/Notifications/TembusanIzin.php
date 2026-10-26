<?php

namespace App\Notifications;

use App\Models\PengajuanIzin;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;

// Tembusan (CC read-only): tanpa tombol approve, murni informasi agar
// pemegang jabatan struktural (mis. Wakasek Kurikulum) bisa antisipasi KBM.
// Database + webpush dikirim langsung; email lewat ApprovalMail (queue).
class TembusanIzin extends Notification
{
    public function __construct(public PengajuanIzin $pengajuan, public string $momen) {}

    public function via($notifiable): array
    {
        return ['database', 'webpush'];
    }

    public function toMail($notifiable): MailMessage
    {
        $p = $this->pengajuan;
        $namaPegawai = $p->pegawai?->nama_lengkap ?? 'Pegawai';
        $label = strtoupper($p->jenis_izin);

        return (new MailMessage)
            ->subject("Tembusan {$label} {$namaPegawai} — {$this->momenLabel()}")
            ->greeting('Yth. '.($notifiable->name ?? 'Bapak/Ibu').',')
            ->line("Tembusan: {$namaPegawai} {$this->momenLabel()} {$label}.")
            ->line("Periode: {$p->tanggal_mulai?->format('d M Y')} s.d. {$p->tanggal_selesai?->format('d M Y')}")
            ->action('Lihat Pengajuan', route('pengajuan-izin.index'))
            ->salutation('Hormat kami, Sistem HRIS');
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        $p = $this->pengajuan;
        $namaPegawai = $p->pegawai?->nama_lengkap ?? 'Pegawai';
        $label = strtoupper($p->jenis_izin);

        return (new WebPushMessage)
            ->title("Tembusan {$label} {$namaPegawai}")
            ->body("{$namaPegawai} {$this->momenLabel()} {$label} ({$p->tanggal_mulai?->format('d M')} - {$p->tanggal_selesai?->format('d M')}).")
            ->badge(asset('/icons/icon-192.png'))
            ->icon(asset('/icons/icon-192.png'));
    }

    public function toDatabase($notifiable): array
    {
        $p = $this->pengajuan;
        $pegawai = $p->pegawai;

        return [
            'type' => 'tembusan_izin',
            'pengajuan_id' => $p->id,
            'pegawai_id' => $pegawai?->id,
            'pegawai_nama' => $pegawai?->nama_lengkap ?? '(tanpa nama)',
            'jenis_izin' => $p->jenis_izin,
            'momen' => $this->momen,
            'tanggal_mulai' => $p->tanggal_mulai?->format('Y-m-d'),
            'tanggal_selesai' => $p->tanggal_selesai?->format('Y-m-d'),
            'created_at' => now()->toISOString(),
        ];
    }

    private function momenLabel(): string
    {
        return match ($this->momen) {
            'diajukan' => 'mengajukan',
            'disetujui' => 'disetujui',
            'ditolak' => 'ditolak',
            default => $this->momen,
        };
    }
}
