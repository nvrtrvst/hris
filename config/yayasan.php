<?php

return [
    /*
     * Profil yayasan untuk kop surat (laporan PDF, surat, dll).
     * Ganti dengan data asli yayasan. Bisa di-override lewat env.
     */
    'name' => env('YAYASAN_NAME', 'YAYASAN PENDIDIKAN NUURUL MUTTAQIIN'),
    'tagline' => env('YAYASAN_TAGLINE', 'Sistem Informasi Manajemen SDM & Presensi'),
    'address' => env('YAYASAN_ADDRESS', 'Jl. Pendidikan No. 1, Kota – Provinsi'),
    'phone' => env('YAYASAN_PHONE', '(021) 0000-0000'),
    'email' => env('YAYASAN_EMAIL', 'info@nuurulmuttaqiin.or.id'),
    'website' => env('YAYASAN_WEBSITE', 'www.nuurulmuttaqiin.or.id'),
];
