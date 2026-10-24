<?php

namespace App\Constants;

class KpiProfiles
{
    public static function forJabatan(string $nama, bool $isGuru, bool $isSupervisor): array
    {
        if (str_contains($nama, 'BK') || str_contains(mb_strtolower($nama), 'terapis')) {
            return ['label' => 'Layanan Konseling', 'indicators' => [
                ['layanan_selesai', 'Pemenuhan layanan terjadwal', 20, 'numeric', null, 100, 'higher'],
                ['dokumentasi', 'Dokumentasi dan tindak lanjut', 20, 'rubric', null, null, null],
                ['mutu_layanan', 'Mutu layanan', 25, 'rubric', null, null, null],
                ['progres_kasus', 'Progres tindak lanjut', 15, 'rubric', null, null, null],
                ['kerahasiaan', 'Kerahasiaan dan keselamatan', 10, 'rubric', null, null, null],
                ['kolaborasi', 'Kolaborasi', 5, 'rubric', null, null, null],
                ['kehadiran', 'Kehadiran', 3, 'numeric', 'attendance_rate', 95, 'higher'],
                ['presensi_tepat_waktu', 'Ketepatan waktu saat hadir', 2, 'numeric', 'punctuality_rate', 95, 'higher'],
            ]];
        }

        if (str_contains(mb_strtolower($nama), 'wali kelas')) {
            return ['label' => 'Wali Kelas', 'indicators' => [
                ['jp_mengajar', 'Pemenuhan JP mengajar', 20, 'numeric', 'teaching_jp_rate', 95, 'higher'],
                ['administrasi_kelas', 'Administrasi dan data kelas', 20, 'rubric', null, null, null],
                ['bimbingan_siswa', 'Bimbingan siswa dan komunikasi orang tua', 20, 'rubric', null, null, null],
                ['mutu_mengajar', 'Mutu mengajar', 15, 'rubric', null, null, null],
                ['penilaian_siswa', 'Penilaian dan umpan balik siswa', 10, 'rubric', null, null, null],
                ['kemajuan_siswa', 'Kemajuan belajar siswa', 10, 'rubric', null, null, null],
                ['kehadiran', 'Kehadiran', 3, 'numeric', 'attendance_rate', 95, 'higher'],
                ['presensi_tepat_waktu', 'Ketepatan waktu saat hadir', 2, 'numeric', 'punctuality_rate', 95, 'higher'],
            ]];
        }

        if ($isGuru) {
            return ['label' => 'Pendidik', 'indicators' => [
                ['jp_mengajar', 'Pemenuhan JP mengajar', 25, 'numeric', 'teaching_jp_rate', 95, 'higher'],
                ['perangkat_ajar', 'Persiapan dan administrasi pembelajaran', 15, 'rubric', null, null, null],
                ['mutu_mengajar', 'Mutu mengajar', 20, 'rubric', null, null, null],
                ['penilaian_siswa', 'Penilaian dan umpan balik siswa', 15, 'rubric', null, null, null],
                ['kemajuan_siswa', 'Kemajuan belajar siswa', 10, 'rubric', null, null, null],
                ['kolaborasi', 'Kolaborasi dan tanggung jawab', 5, 'rubric', null, null, null],
                ['kehadiran', 'Kehadiran', 5, 'numeric', 'attendance_rate', 95, 'higher'],
                ['presensi_tepat_waktu', 'Ketepatan waktu saat hadir', 5, 'numeric', 'punctuality_rate', 95, 'higher'],
            ]];
        }

        if (str_contains(mb_strtolower($nama), 'bendahara') || str_contains(mb_strtolower($nama), 'kasir')) {
            return ['label' => 'Keuangan', 'indicators' => [
                ['akurasi_transaksi', 'Akurasi transaksi dan rekonsiliasi', 30, 'rubric', null, null, null],
                ['ketepatan_waktu', 'Ketepatan waktu proses', 20, 'rubric', null, null, null],
                ['kepatuhan', 'Kontrol dan kepatuhan', 25, 'rubric', null, null, null],
                ['bukti_transaksi', 'Kelengkapan bukti transaksi', 10, 'rubric', null, null, null],
                ['layanan', 'Mutu layanan', 10, 'rubric', null, null, null],
                ['kehadiran', 'Kehadiran', 3, 'numeric', 'attendance_rate', 95, 'higher'],
                ['presensi_tepat_waktu', 'Ketepatan waktu saat hadir', 2, 'numeric', 'punctuality_rate', 95, 'higher'],
            ]];
        }

        if (str_contains(mb_strtolower($nama), 'pustakawan') || str_contains(mb_strtolower($nama), 'perpustakaan')
            || str_contains(mb_strtolower($nama), 'laboran') || str_contains(mb_strtolower($nama), 'laboratorium')) {
            if ($isSupervisor) {
                return ['label' => 'Kepala Fasilitas Belajar', 'indicators' => [
                    ['target_unit', 'Capaian rencana unit/fasilitas', 20, 'rubric', null, null, null],
                    ['pembinaan', 'Pembinaan petugas', 20, 'rubric', null, null, null],
                    ['kesiapan_layanan', 'Kesiapan layanan dan fasilitas', 20, 'rubric', null, null, null],
                    ['inventaris', 'Inventaris dan pemeliharaan', 15, 'rubric', null, null, null],
                    ['keselamatan', 'Keselamatan dan kepatuhan', 15, 'rubric', null, null, null],
                    ['dukungan_belajar', 'Dukungan kegiatan belajar', 5, 'rubric', null, null, null],
                    ['kehadiran', 'Kehadiran', 3, 'numeric', 'attendance_rate', 95, 'higher'],
                    ['presensi_tepat_waktu', 'Ketepatan waktu saat hadir', 2, 'numeric', 'punctuality_rate', 95, 'higher'],
                ]];
            }

            return ['label' => 'Fasilitas dan Layanan Belajar', 'indicators' => [
                ['kesiapan_layanan', 'Kesiapan layanan dan fasilitas', 25, 'rubric', null, null, null],
                ['inventaris', 'Inventaris dan pemeliharaan', 25, 'rubric', null, null, null],
                ['dukungan_belajar', 'Dukungan kegiatan belajar', 20, 'rubric', null, null, null],
                ['keselamatan', 'Keselamatan dan kepatuhan', 15, 'rubric', null, null, null],
                ['mutu_layanan', 'Mutu layanan', 10, 'rubric', null, null, null],
                ['kehadiran', 'Kehadiran', 3, 'numeric', 'attendance_rate', 95, 'higher'],
                ['presensi_tepat_waktu', 'Ketepatan waktu saat hadir', 2, 'numeric', 'punctuality_rate', 95, 'higher'],
            ]];
        }

        if ($isSupervisor) {
            return ['label' => 'Kepemimpinan', 'indicators' => [
                ['target_unit', 'Capaian rencana unit', 25, 'rubric', null, null, null],
                ['supervisi', 'Supervisi dan tindak lanjut', 20, 'rubric', null, null, null],
                ['pembinaan', 'Pembinaan pegawai', 20, 'rubric', null, null, null],
                ['tata_kelola', 'Tata kelola dan kepatuhan', 15, 'rubric', null, null, null],
                ['layanan_pemangku', 'Penanganan kebutuhan siswa/orang tua', 10, 'rubric', null, null, null],
                ['kehadiran', 'Kehadiran', 5, 'numeric', 'attendance_rate', 95, 'higher'],
                ['presensi_tepat_waktu', 'Ketepatan waktu saat hadir', 5, 'numeric', 'punctuality_rate', 95, 'higher'],
            ]];
        }

        if (str_contains(mb_strtolower($nama), 'satpam') || str_contains(mb_strtolower($nama), 'keamanan')
            || str_contains(mb_strtolower($nama), 'kebersihan') || str_contains(mb_strtolower($nama), 'kebun')
            || str_contains(mb_strtolower($nama), 'office boy') || str_contains(mb_strtolower($nama), 'pesuruh')) {
            return ['label' => 'Operasional Unit', 'indicators' => [
                ['checklist_tugas', 'Penyelesaian checklist tugas', 35, 'rubric', null, null, null],
                ['keselamatan', 'Keselamatan dan respons insiden', 25, 'rubric', null, null, null],
                ['kualitas_area', 'Kualitas area/fasilitas', 20, 'rubric', null, null, null],
                ['pelaporan', 'Pelaporan kondisi dan peralatan', 10, 'rubric', null, null, null],
                ['kehadiran', 'Kehadiran', 5, 'numeric', 'attendance_rate', 95, 'higher'],
                ['presensi_tepat_waktu', 'Ketepatan waktu saat hadir', 5, 'numeric', 'punctuality_rate', 95, 'higher'],
            ]];
        }

        return ['label' => 'Administrasi dan Layanan', 'indicators' => [
            ['tugas_selesai', 'Penyelesaian tugas sesuai tenggat', 30, 'rubric', null, null, null],
            ['akurasi', 'Akurasi data dan dokumen', 25, 'rubric', null, null, null],
            ['layanan', 'Mutu dan kecepatan layanan', 20, 'rubric', null, null, null],
            ['kepatuhan', 'Kepatuhan dan keamanan data', 15, 'rubric', null, null, null],
            ['kehadiran', 'Kehadiran', 5, 'numeric', 'attendance_rate', 95, 'higher'],
            ['presensi_tepat_waktu', 'Ketepatan waktu saat hadir', 5, 'numeric', 'punctuality_rate', 95, 'higher'],
        ]];
    }
}
