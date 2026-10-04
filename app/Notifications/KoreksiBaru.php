<?php

namespace App\Notifications;

use App\Models\PengajuanKoreksi;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;

// Sinkron (tanpa ShouldQueue): deployment tidak menjalankan queue worker,
// jadi notifikasi dikirim langsung agar tidak menumpuk di tabel jobs.
class KoreksiBaru extends Notification
{
    public function __construct(public PengajuanKoreksi $koreksi) {}

    public function via($notifiable): array
    {
        return ['database', 'webpush', 'mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $k = $this->koreksi;
        $namaPegawai = $k->pegawai?->nama_lengkap ?? 'Pegawai';

        return (new MailMessage)
            ->subject('Pengajuan Koreksi Presensi Baru — Perlu Persetujuan')
            ->greeting('Yth. '.($notifiable->name ?? 'Approver').',')
            ->line("{$namaPegawai} mengajukan koreksi presensi.")
            ->line("Tanggal: {$k->tanggal?->format('d M Y')}")
            ->line("Jam masuk: {$k->presensi?->jam_masuk} · Usulan jam keluar: {$k->nilai_baru}")
            ->line('Alasan: '.$this->labelAlasan())
            ->action('Tinjau Pengajuan', route('koreksi-presensi.index'))
            ->salutation('Hormat kami, Sistem HRIS');
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        $k = $this->koreksi;
        $namaPegawai = $k->pegawai?->nama_lengkap ?? 'Pegawai';

        return (new WebPushMessage)
            ->title('Pengajuan Koreksi Presensi Baru')
            ->body("{$namaPegawai} mengajukan koreksi jam keluar {$k->tanggal?->format('d M')} ({$k->nilai_baru}).")
            ->badge(asset('/icons/icon-192.png'))
            ->icon(asset('/icons/icon-192.png'));
    }

    public function toDatabase($notifiable): array
    {
        $k = $this->koreksi;

        return [
            'type' => 'koreksi_baru',
            'koreksi_id' => $k->id,
            'pegawai_id' => $k->pegawai_id,
            'pegawai_nama' => $k->pegawai?->nama_lengkap ?? '(tanpa nama)',
            'tanggal' => $k->tanggal?->format('Y-m-d'),
            'nilai_baru' => $k->nilai_baru,
            'alasan' => $k->alasan,
            'created_at' => $k->created_at?->toISOString(),
        ];
    }

    private function labelAlasan(): string
    {
        return match ($this->koreksi->alasan) {
            'lupa_presensi' => 'Lupa presensi',
            'hp_rusak' => 'HP rusak',
            'dinas_luar' => 'Dinas luar',
            'rapat' => 'Rapat',
            default => 'Lainnya',
        };
    }
}
