<?php

namespace App\Notifications;

use App\Models\Jadwal;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;

// Sinkron (tanpa ShouldQueue): deployment tidak menjalankan queue worker,
// jadi notifikasi dikirim langsung agar tidak menumpuk di tabel jobs.
class PresensiReminder extends Notification
{
    public function __construct(public Jadwal $jadwal) {}

    public function via($notifiable): array
    {
        return ['webpush'];
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        $mapel = $this->jadwal->mataPelajaran?->nama ?? 'jadwal';
        $jamMulai = $this->jadwal->jam_mulai ? substr((string) $this->jadwal->jam_mulai, 0, 5) : null;
        $unit = $this->jadwal->unitSekolah?->nama ?? $this->jadwal->unitSekolah?->nama_unit;

        return (new WebPushMessage)
            ->title('⏰ Belum absen masuk?')
            ->body("Jadwal {$mapel}".($jamMulai ? " pukul {$jamMulai}" : '').($unit ? " di {$unit}" : '').' sebentar lagi. Jangan lupa presensi ya!')
            ->badge(asset('/icons/icon-192.png'))
            ->icon(asset('/icons/icon-192.png'))
            ->data(['url' => route('presensi.absen')]);
    }
}
