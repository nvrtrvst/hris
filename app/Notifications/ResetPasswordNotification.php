<?php

namespace App\Notifications;

use App\Models\UnitSekolah;
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

        $logos = $this->unitLogos();

        return (new MailMessage)
            ->subject('Atur Ulang Kata Sandi — HRIS Yayasan Nuurul Muttaqiin')
            ->view('emails.reset-password', [
                'name' => $notifiable->name ?: $notifiable->getEmailForPasswordReset(),
                'email' => $notifiable->getEmailForPasswordReset(),
                'url' => $url,
                'appName' => config('app.name', 'HRIS Yayasan'),
                'unitLogos' => $logos,
                'yayasanLogo' => $logos[0]['logo'] ?? null,
            ]);
    }

    /**
     * Daftar unit untuk header email: nama unit + logo yayasan (inline base64
     * agar selalu tampil tanpa perlu Gmail memuat gambar eksternal).
     *
     * @return array<int, array{nama: string, logo: string}>
     */
    private function unitLogos(): array
    {
        static $logoDataUri = null;

        if ($logoDataUri === null) {
            $path = resource_path('views/emails/yayasan-logo.png');
            $logoDataUri = 'data:image/png;base64,'.base64_encode((string) file_get_contents($path));
        }

        return UnitSekolah::query()
            ->orderBy('id')
            ->get(['nama'])
            ->map(fn (UnitSekolah $unit) => [
                'nama' => $unit->nama,
                'logo' => $logoDataUri,
            ])
            ->all();
    }
}
