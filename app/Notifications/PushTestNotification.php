<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;

class PushTestNotification extends Notification
{
    public function via($notifiable): array
    {
        return ['webpush'];
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('🔔 Tes Notifikasi HRIS')
            ->body('Jika ini muncul di notification bar, push notif sudah jalan!')
            ->badge(asset('/icons/icon-192.png'))
            ->icon(asset('/icons/icon-192.png'))
            ->data(['url' => route('presensi.dashboard')]);
    }
}
