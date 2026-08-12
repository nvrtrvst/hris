<?php

namespace App\Helpers;

use App\Models\Pegawai;
use App\Models\User;

class ApprovalHelper
{
    public static function findApproverInUnit(int $unitId, ?int $jabatanId): ?User
    {
        if (! $jabatanId) {
            return null;
        }

        $pegawai = Pegawai::whereHas('units', fn ($q) => $q->where('unit_sekolah.id', $unitId))
            ->whereHas('jabatans', fn ($q) => $q->where('jabatan.id', $jabatanId))
            ->where('status_aktif', 'aktif')
            ->whereNotNull('user_id')
            ->first();

        return $pegawai?->user;
    }

    public static function determineApprovers(Pegawai $pegawai): array
    {
        $primaryUnit = $pegawai->units()->wherePivot('is_primary', true)->first()
            ?? $pegawai->units()->first();

        if (! $primaryUnit) {
            return ['l1_id' => null, 'l2_id' => null, 'has_l1' => false, 'has_l2' => false];
        }

        $primaryJabatan = $pegawai->jabatans()
            ->with('approverL1', 'approverL2')
            ->wherePivot('unit_sekolah_id', $primaryUnit->id)
            ->first();

        if (! $primaryJabatan) {
            return ['l1_id' => null, 'l2_id' => null, 'has_l1' => false, 'has_l2' => false];
        }

        // has_l1 = L1 terkonfigurasi (approver_l1_jabatan_id) DAN approver-nya
        // ditemukan di unit. Fallback admin unit di bawah TIDAK menandai has_l1,
        // sehingga notifikasi tahu kapan harus fallback ke admin unit + superadmin.
        $l1Approver = $primaryJabatan->approver_l1_jabatan_id
            ? self::findApproverInUnit($primaryUnit->id, $primaryJabatan->approver_l1_jabatan_id)
            : null;

        $l2Approver = $primaryJabatan->approver_l2_jabatan_id
            ? self::findApproverInUnit($primaryUnit->id, $primaryJabatan->approver_l2_jabatan_id)
            : null;

        $l1Id = $l1Approver?->id;

        // Fallback: jika L1 tidak ditemukan (approver nonaktif/tidak ada), cari admin unit sebagai L1
        if (! $l1Id) {
            $adminUnit = User::where('unit_sekolah_id', $primaryUnit->id)
                ->role('admin_unit')
                ->first();
            if ($adminUnit) {
                $l1Id = $adminUnit->id;
            }
        }

        $l2Id = $l2Approver?->id;

        if ($l1Id && $l2Id && $l1Id === $l2Id) {
            $l2Id = null;
        }

        return [
            'l1_id' => $l1Id,
            'l2_id' => $l2Id,
            'has_l1' => $l1Approver !== null,
            'has_l2' => $primaryJabatan->approver_l2_jabatan_id !== null,
        ];
    }
}
