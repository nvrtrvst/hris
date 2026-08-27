<?php

namespace App\Notifications;

use App\Models\PengajuanIzin;
use App\Models\PengajuanIzinComment;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;

class IzinReply extends Notification
{
    public function __construct(
        public PengajuanIzin $pengajuan,
        public PengajuanIzinComment $comment,
        public User $sender,
    ) {}

    public function via($notifiable): array
    {
        return ['database', 'webpush'];
    }

    public function toMail($notifiable): MailMessage
    {
        $p = $this->pengajuan;
        $label = strtoupper($p->jenis_izin);
        $nama = $p->pegawai?->nama_lengkap ?? 'Pegawai';

        return (new MailMessage)
            ->subject("Balasan pada Pengajuan {$label} — {$nama}")
            ->greeting('Yth. '.($notifiable->name ?? '').',')
            ->line("{$this->sender->name} membalas di thread pengajuan {$label} milik {$nama}.")
            ->line('"'.$this->comment->message.'"')
            ->action('Lihat Thread', route('pengajuan-izin.index'))
            ->salutation('Hormat kami, Sistem HRIS');
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        $p = $this->pengajuan;
        $label = strtoupper($p->jenis_izin);

        return (new WebPushMessage)
            ->title("Balasan — Pengajuan {$label}")
            ->body("{$this->sender->name}: {$this->comment->message}")
            ->badge(asset('/icons/icon-192.png'))
            ->icon(asset('/icons/icon-192.png'));
    }

    public function toDatabase($notifiable): array
    {
        $p = $this->pengajuan;

        return [
            'type' => 'izin_reply',
            'pengajuan_id' => $p->id,
            'comment_id' => $this->comment->id,
            'sender_id' => $this->sender->id,
            'sender_name' => $this->sender->name,
            'message' => $this->comment->message,
            'jenis_izin' => $p->jenis_izin,
            'pegawai_nama' => $p->pegawai?->nama_lengkap ?? '(tanpa nama)',
        ];
    }
}
