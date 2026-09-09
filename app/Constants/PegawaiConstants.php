<?php

namespace App\Constants;

/**
 * Master-data pegawai yang tidak punya tabel sendiri.
 * Satu-satunya sumber kebenaran — dipakai form, template import, dan validasi.
 *
 * Status kepegawaian TIDAK lagi di sini — sudah pindah ke tabel referensi
 * `status_kepegawaian` (lihat App\Models\StatusKepegawaian, Tier B) supaya
 * label/kelompok bisa diubah lewat DB tanpa deploy.
 */
class PegawaiConstants
{
    /**
     * Jenjang pendidikan terakhir (standar Dapodik).
     * Dipakai dropdown template import supaya data konsisten.
     */
    public const PENDIDIKAN_TERAKHIR = [
        'SD/Sederajat',
        'SMP/Sederajat',
        'SMA/Sederajat',
        'SMK/Sederajat',
        'D1',
        'D2',
        'D3',
        'D4',
        'S1',
        'S2',
        'S3',
    ];
}
