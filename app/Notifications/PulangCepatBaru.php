<?php

namespace App\Notifications;

use App\Models\PengajuanPulangCepat;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;

// Sinkron (tanpa ShouldQueue): deployment tidak menjalankan queue worker.
class PulangCepatBaru extends Notification
{
    public function __construct(public PengajuanPulangCepat $pengajuan) {}

    public function via($notifiable): array
    {
        return ['database', 'webpush', 'mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $p = $this->pengajuan;
        $namaPegawai = $p->pegawai?->nama_lengkap ?? 'Pegawai';

        return (new MailMessage)
            ->subject('Pengajuan Izin Pulang Cepat — Perlu Persetujuan')
            ->greeting('Yth. '.($notifiable->name ?? 'Approver').',')
            ->line("{$namaPegawai} mengajukan izin pulang cepat.")
            ->line("Tanggal: {$p->tanggal?->format('d M Y')} · Jam diminta: ".substr((string) $p->jam_diminta, 0, 5))
            ->line('Alasan: '.$this->labelAlasan())
            ->action('Tinjau Pengajuan', route('pulang-cepat.index'))
            ->salutation('Hormat kami, Sistem HRIS');
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        $p = $this->pengajuan;

        return (new WebPushMessage)
            ->title('Pengajuan Izin Pulang Cepat')
            ->body(($p->pegawai?->nama_lengkap ?? 'Pegawai').' minta pulang pukul '.substr((string) $p->jam_diminta, 0, 5)." ({$p->tanggal?->format('d M')}).")
            ->badge(asset('/icons/icon-192.png'))
            ->icon(asset('/icons/icon-192.png'));
    }

    public function toDatabase($notifiable): array
    {
        $p = $this->pengajuan;

        return [
            'type' => 'pulang_cepat_baru',
            'pengajuan_id' => $p->id,
            'pegawai_id' => $p->pegawai_id,
            'pegawai_nama' => $p->pegawai?->nama_lengkap ?? '(tanpa nama)',
            'tanggal' => $p->tanggal?->format('Y-m-d'),
            'jam_diminta' => $p->jam_diminta,
            'alasan' => $p->alasan,
            'created_at' => $p->created_at?->toISOString(),
        ];
    }

    private function labelAlasan(): string
    {
        return match ($this->pengajuan->alasan) {
            'sakit' => 'Sakit',
            'urusan_keluarga' => 'Urusan keluarga',
            'keperluan_pribadi' => 'Keperluan pribadi',
            'dinas_luar' => 'Dinas luar',
            default => 'Lainnya',
        };
    }
}
