<?php

return [
    /*
     * URL API publik untuk sinkronisasi hari libur nasional Indonesia.
     * Harus HTTPS. Override lewat env HARILIBUR_API_URL bila perlu.
     * Source of truth tetap database; API hanya salah satu sumber isi.
     */
    'harilibur_api_url' => env('HARILIBUR_API_URL', 'https://indonesia-holiday-api.vercel.app/api?year='),
];
