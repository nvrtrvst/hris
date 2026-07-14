<?php

// Konfigurasi integrasi ke app keuangan (Next.js) untuk mengambil data siswa.
// URL & key diisi via .env (jangan hardcode nilai asli di sini).

return [
    // Base URL app keuangan (biasanya http://localhost:<port> di dev,
    // atau http://127.0.0.1:<port> di produksi satu server).
    'url' => env('KEUANGAN_API_URL', 'http://localhost:3000'),

    // Harus SAMA dengan INTERNAL_API_KEY di .env app keuangan.
    'key' => env('KEUANGAN_API_KEY', 'change-me-in-production'),
];
