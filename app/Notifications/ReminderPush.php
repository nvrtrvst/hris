<?php

namespace App\Notifications;

use App\Models\Reminder;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;

class ReminderPush extends Notification
{
    public function __construct(public Reminder $reminder) {}

    public function via($notifiable): array
    {
        return ['database', 'webpush'];
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        $typeEmoji = match ($this->reminder->type) {
            'presensi' => '⏰',
            'cuti' => '🏖️',
            'deadline' => '📋',
            default => '🔔',
        };

        return (new WebPushMessage)
            ->title($typeEmoji.' '.$this->reminder->title)
            ->body($this->reminder->message)
            ->badge(asset('/icons/icon-192.png'))
            ->icon(asset('/icons/icon-192.png'));
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'reminder',
            'reminder_id' => $this->reminder->id,
            'title' => $this->reminder->title,
            'message' => $this->reminder->message,
            'reminder_type' => $this->reminder->type,
        ];
    }
}
