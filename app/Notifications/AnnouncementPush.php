<?php

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;

class AnnouncementPush extends Notification
{
    public function __construct(public Announcement $announcement) {}

    public function via($notifiable): array
    {
        return ['webpush'];
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->announcement->title)
            ->badge(asset('/icons/icon-192.png'))
            ->icon($this->announcement->image_url ?: asset('/icons/icon-192.png'));
    }
}
