<?php

namespace App\Constants;

/**
 * Master-data pegawai yang tidak punya tabel sendiri.
 * Satu-satunya sumber kebenaran — dipakai form, template import, dan validasi.
 */
class PegawaiConstants
{
    /**
     * Status kepegawaian (Dapodik): dropdown template + validasi in:.
     */
    public const STATUS_KEPEGAWAIAN = [
        'tetap',
        'kontrak',
        'honorer',
        'gtt',
    ];

    /**
     * Label tampilan status kepegawaian (dipakai export Excel).
     */
    public const STATUS_KEPEGAWAIAN_LABELS = [
        'tetap' => 'Tetap',
        'kontrak' => 'Kontrak',
        'honorer' => 'Honorer',
        'gtt' => 'GTT (Guru Tidak Tetap)',
    ];

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
