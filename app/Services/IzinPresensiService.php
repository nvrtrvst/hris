<?php

namespace App\Services;

use App\Helpers\PayrollLockHelper;
use App\Models\AuditPresensi;
use App\Models\PengajuanIzin;
use App\Models\Presensi;
use Carbon\CarbonPeriod;

class IzinPresensiService
{
    public static function generate(PengajuanIzin $pengajuan): void
    {
        $pegawai = $pengajuan->pegawai;
        $unit = $pegawai?->units()->wherePivot('is_primary', true)->first()
            ?? $pegawai?->units()->first();

        if (! $unit) {
            return;
        }

        foreach (CarbonPeriod::create($pengajuan->tanggal_mulai, $pengajuan->tanggal_selesai) as $date) {
            if ($date->isWeekend() || PayrollLockHelper::isPeriodLocked($pengajuan->pegawai_id, $date)) {
                continue;
            }

            $presensi = Presensi::updateOrCreate(
                [
                    'pegawai_id' => $pengajuan->pegawai_id,
                    'tanggal' => $date->format('Y-m-d'),
                ],
                [
                    'unit_sekolah_id' => $unit->id,
                    // Default kolom 'mengajar'; row izin dicatat sebagai kehadiran kantor.
                    'tipe_presensi' => 'kantor',
                    'keterangan' => 'Dari Pengajuan Izin/Cuti',
                ]
            );

            $presensi->status = $pengajuan->jenis_izin;
            $presensi->save();

            AuditPresensi::log(
                $presensi->id,
                'generate_izin',
                'status',
                null,
                $pengajuan->jenis_izin,
                "Izin {$pengajuan->jenis_izin} {$date->format('Y-m-d')} (pengajuan #{$pengajuan->id})"
            );
        }
    }
}
