<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Kustomisasi isi email Lupa Kata Sandi
        ResetPassword::toMailUsing(function (object $notifiable, string $token) {
            return (new MailMessage)
                ->subject('Pemberitahuan Pemulihan Kata Sandi - Yayasan Nuurul Muttaqiin')
                ->greeting('Yth. '.$notifiable->name.',')
                ->line('Kami menerima permohonan untuk melakukan pemulihan kata sandi pada akun portal HRIS Anda yang terdaftar di sistem Yayasan Nuurul Muttaqiin.')
                ->line('Demi keamanan data Anda, silakan klik tombol di bawah ini untuk mengatur ulang kata sandi Anda:')
                ->action('Atur Ulang Kata Sandi', url(route('password.reset', [
                    'token' => $token,
                    'email' => $notifiable->getEmailForPasswordReset(),
                ], false)))
                ->line('Tautan pemulihan ini bersifat rahasia dan hanya akan berlaku selama 60 menit sejak email ini dikirimkan.')
                ->line('Apabila Anda tidak pernah mengajukan permohonan ini, Anda tidak perlu melakukan tindakan apa pun. Keamanan akun Anda tetap terjaga.')
                ->salutation('Hormat kami, Tim IT Yayasan Nuurul Muttaqiin');
        });
    }
}
