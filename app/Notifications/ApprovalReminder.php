<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;

class ApprovalReminder extends Notification
{
    public function __construct(
        public string $judul,
        public string $pesan,
        public string $url,
        public array $data = [],
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'webpush'];
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->data + [
            'message' => $this->pesan,
            'url' => $this->url,
        ];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->judul)
            ->body($this->pesan)
            ->data(['url' => $this->url]);
    }
}
