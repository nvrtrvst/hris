<?php

use App\Providers\AppServiceProvider;
use NotificationChannels\WebPush\WebPushServiceProvider;

return [
    AppServiceProvider::class,
    // Wajib didaftarkan manual (Laravel 11+ tanpa auto-discovery) — tanpa ini
    // semua notifikasi via channel 'webpush' gagal 'Driver [webpush] not supported'.
    WebPushServiceProvider::class,
];
