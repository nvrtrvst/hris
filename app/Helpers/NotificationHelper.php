<?php

namespace App\Helpers;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class NotificationHelper
{
    /**
     * Kirim notifikasi secara sinkron tapi aman: kegagalan channel (mis. SMTP down)
     * tidak boleh memblokir operasi inti (submit/approve/reject izin).
     * Data operasi inti sudah di-commit — error notifikasi cukup di-log saja.
     */
    public static function sendSafely($notifiable, Notification $notification): void
    {
        if (! $notifiable) {
            return;
        }

        try {
            $notifiable->notify($notification);
        } catch (\Throwable $e) {
            Log::warning('Gagal mengirim notifikasi', [
                'notification' => get_class($notification),
                'notifiable_id' => method_exists($notifiable, 'getKey') ? $notifiable->getKey() : null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
