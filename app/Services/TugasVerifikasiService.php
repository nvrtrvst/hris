<?php

namespace App\Services;

use App\Helpers\PayrollLockHelper;
use App\Models\AuditLog;
use App\Models\LaporanPiket;
use App\Models\Pegawai;
use App\Models\PengajuanIzin;
use App\Models\User;

class TugasVerifikasiService
{
    /**
     * Verifikasi tugas dari entry kelas kosong piket yang disetujui admin.
     * Hanya berlaku untuk sakit bersurat dan periode payroll yang belum dikunci.
     */
    public static function dariPiket(LaporanPiket $laporan, array $entry, User $user): ?PengajuanIzin
    {
        if ($laporan->status !== LaporanPiket::STATUS_SUBMITTED) {
            return null;
        }

        if (! ($entry['ada_amanat'] ?? false) || ! ($entry['tersampaikan'] ?? false)) {
            return null;
        }

        if (($entry['validasi'] ?? null) !== 'cocok') {
            return null;
        }

        $pegawaiId = (int) ($entry['guru_pegawai_id'] ?? 0);
        if ($pegawaiId <= 0 || ! $laporan->tanggal) {
            return null;
        }

        $guru = Pegawai::find($pegawaiId);
        if (! $guru || ! $laporan->unit_sekolah_id || ! $guru->belongsToUnit($laporan->unit_sekolah_id)) {
            return null;
        }

        if (PayrollLockHelper::isPeriodLocked($pegawaiId, $laporan->tanggal)) {
            return null;
        }

        $tanggal = $laporan->tanggal->toDateString();
        $izin = PengajuanIzin::where('pegawai_id', $pegawaiId)
            ->where('status', 'disetujui')
            ->where('jenis_izin', 'sakit')
            ->where('dengan_surat_dokter', true)
            ->whereDate('tanggal_mulai', '<=', $tanggal)
            ->whereDate('tanggal_selesai', '>=', $tanggal)
            ->lockForUpdate()
            ->first();

        if (! $izin) {
            return null;
        }

        if (! $izin->tugas_terverifikasi) {
            $lama = ['tugas_terverifikasi' => false, 'tugas_diverifikasi_at' => null, 'tugas_diverifikasi_oleh' => null];
            $izin->tugas_terverifikasi = true;
            $izin->tugas_diverifikasi_at = now();
            $izin->tugas_diverifikasi_oleh = $user->id;
            $izin->save();

            AuditLog::log($izin, 'verifikasi_tugas', $lama, [
                'tugas_terverifikasi' => true,
                'sumber' => 'piket',
                'laporan_piket_id' => $laporan->id,
                'oleh' => $user->id,
            ]);
        }

        return $izin;
    }
}
