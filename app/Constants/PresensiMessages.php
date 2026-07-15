<?php

namespace App\Constants;

/**
 * Konstanta untuk pesan-pesan terkait presensi
 * Terpusat untuk memudahkan maintenance dan lokalization di masa depan
 */
class PresensiMessages
{
    // Geofence Messages
    public const GEOFENCE_OUTSIDE = 'Anda berada di luar jangkauan Unit Sekolah. Jarak Anda: %s meter (Batas: %sm)';

    public const GEOFENCE_INSIDE = 'Dalam radius %s (%sm)';

    public const GEOFENCE_ACCURACY_ZERO = 'Akurasi lokasi 0 meter (dicurigai lokasi palsu / mock GPS). Gunakan GPS asli.';

    public const GEOFENCE_ACCURACY_POOR = 'Akurasi GPS buruk (%sm > batas %sm). Coba dari tempat terbuka.';

    // Presensi Status Messages
    public const ABSEN_MASUK_SUCCESS = 'Absen masuk berhasil dicatat! Jarak: %sm';

    public const ABSEN_KELUAR_SUCCESS = 'Absen keluar berhasil dicatat! Jarak: %sm';

    public const LEMBUR_MASUK_SUCCESS = 'Lembur masuk berhasil dicatat! Jarak: %sm';

    public const LEMBUR_KELUAR_SUCCESS = 'Lembur keluar berhasil dicatat! Jarak: %sm';

    // Validation Messages
    public const PEMILIH_JADWAL_DULU = 'Pilih jadwal terlebih dahulu.';

    public const PEGAWAI_TIDAK_PUNYA_UNIT = 'Pegawai tidak memiliki unit sekolah.';

    public const SUDAH_ABSEN_MASUK = 'Anda sudah melakukan absen masuk.';

    public const SUDAH_ABSEN_KELUAR = 'Anda sudah melakukan absen keluar.';

    public const BELUM_ABSEN_MASUK = 'Anda belum absen masuk.';

    // Label
    public const LABEL_ABSEN = 'Absen';

    public const LABEL_LEMBUR = 'Lembur';

    public const LABEL_MASUK = 'masuk';

    public const LABEL_KELUAR = 'keluar';

    // Unit Types
    public const UNIT_LEMBUR = 'lembur';

    public const UNIT_REGULER = 'reguler';
}
