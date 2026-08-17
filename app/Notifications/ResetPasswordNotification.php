<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi reset kata sandi dengan template email ber-branding.
 *
 * Sinkron (tanpa ShouldQueue) — deployment tidak menjalankan queue worker,
 * konsisten dengan notifikasi lain di aplikasi ini.
 */
class ResetPasswordNotification extends Notification
{
    public function __construct(public string $token) {}

    /**
     * @return array<int, string>
     */
    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->subject('Atur Ulang Kata Sandi — HRIS Yayasan Nuurul Muttaqiin')
            ->view('emails.reset-password', [
                'name' => $notifiable->name ?: $notifiable->getEmailForPasswordReset(),
                'email' => $notifiable->getEmailForPasswordReset(),
                'url' => $url,
                'appName' => config('app.name', 'HRIS Yayasan'),
            ]);
    }
}
