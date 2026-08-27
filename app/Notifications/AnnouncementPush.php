<?php

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\WebPushMessage;

class AnnouncementPush extends Notification
{
    public function __construct(public Announcement $announcement) {}

    public function via($notifiable): array
    {
        return ['database', 'webpush'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'announcement',
            'announcement_id' => $this->announcement->id,
            'title' => $this->announcement->title,
            'message' => Str::limit(strip_tags($this->announcement->body), 140),
            'image' => $this->announcement->image_url,
            'file_url' => $this->announcement->file_url,
        ];
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->announcement->title)
            ->badge(asset('/icons/icon-192.png'))
            ->icon($this->announcement->image_url ?: asset('/icons/icon-192.png'));
    }
}
