<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApprovalMail extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $lines
     */
    public function __construct(
        public string $subject,
        public string $greeting,
        public array $lines = [],
        public ?string $actionLabel = null,
        public ?string $actionUrl = null,
        public string $salutation = 'Hormat kami, Sistem HRIS',
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->subject)
            ->greeting('Yth. '.($notifiable->name ?? 'Bapak/Ibu').',');

        foreach ($this->lines as $line) {
            $mail->line($line);
        }

        if ($this->actionLabel && $this->actionUrl) {
            $mail->action($this->actionLabel, $this->actionUrl);
        }

        return $mail->salutation($this->salutation);
    }
}
