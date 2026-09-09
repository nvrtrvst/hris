<?php

namespace App\Constants;

/**
 * Master-data pegawai yang tidak punya tabel sendiri.
 * Satu-satunya sumber kebenaran — dipakai form, template import, dan validasi.
 */
class PegawaiConstants
{
    /**
     * Status kepegawaian (format GTYS Yayasan): dropdown template + validasi in:.
     *
     * Tiga opsi untuk guru: GTYS (Guru Tetap Yayasan), GTT (Guru Tidak Tetap),
     * Guru Pemula. Tiga opsi untuk non-guru (staf/pegawai): PTT (Pegawai Tidak
     * Tetap), Pegawai Tidak Tetap, Pegawai Pemula.
     */
    public const STATUS_KEPEGAWAIAN = [
        'guru_tetap_yayasan',
        'guru_tidak_tetap',
        'guru_pemula',
        'ptt',
        'pegawai_tidak_tetap',
        'pegawai_pemula',
    ];

    /**
     * Label tampilan status kepegawaian (dipakai export Excel + React).
     */
    public const STATUS_KEPEGAWAIAN_LABELS = [
        'guru_tetap_yayasan' => 'GTYS (Guru Tetap Yayasan)',
        'guru_tidak_tetap' => 'GTT (Guru Tidak Tetap)',
        'guru_pemula' => 'Guru Pemula',
        'ptt' => 'PTT (Pegawai Tidak Tetap)',
        'pegawai_tidak_tetap' => 'Pegawai Tidak Tetap',
        'pegawai_pemula' => 'Pegawai Pemula',
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
