<?php

return [

    /*
     * Path logo yayasan (relatif terhadap root disk image_disk) yang dipakai
     * di kop surat Laporan KCD saat yang mengunduh adalah superadmin.
     * Letakkan berkas logo di storage disk image_disk (mis. storage/app/public/unit_logos/yayasan.png).
     * Jika kosong/null atau berkas tidak ditemukan, fallback ke logo unit.
     */
    'yayasan_logo' => env('KCD_YAYASAN_LOGO', 'unit_logos/yayasan.png'),

];
