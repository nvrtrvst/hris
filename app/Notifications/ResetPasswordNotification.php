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
    public function __construct(
        public string $token,
        public ?string $resetUrl = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $url = $this->resetUrl ?? route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], true);

        $logos = $this->unitLogos();
        $yayasanLogo = url('logo.png');

        return (new MailMessage)
            ->subject('Atur Ulang Kata Sandi — HRIS Yayasan Nuurul Muttaqiin')
            ->view('emails.reset-password', [
                'name' => $notifiable->name ?: $notifiable->getEmailForPasswordReset(),
                'email' => $notifiable->getEmailForPasswordReset(),
                'url' => $url,
                'appName' => config('app.name', 'HRIS Yayasan'),
                'unitLogos' => $logos,
                'yayasanLogo' => $yayasanLogo,
            ]);
    }

    /**
     * Daftar unit untuk header email: nama unit + logo publik ber-URL absolut.
     *
     * @return array<int, array{nama: string, logo: string}>
     */
    private function unitLogos(): array
    {
        $fallback = url('logo.png');

        return UnitSekolah::query()
            ->orderBy('id')
            ->get(['nama', 'logo'])
            ->map(fn (UnitSekolah $unit) => [
                'nama' => $unit->nama,
                'logo' => $this->publicLogoUrl($unit->logo) ?: $fallback,
            ])
            ->all();
    }

    private function publicLogoUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $path = ltrim($path, '/');

        return url(str_starts_with($path, 'storage/') ? '/'.$path : '/storage/'.$path);
    }
}
