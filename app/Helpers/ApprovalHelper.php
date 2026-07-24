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
            ->whereNotNull('user_id')
            ->first();

        return $pegawai?->user;
    }

    public static function determineApprovers(Pegawai $pegawai): array
    {
        $primaryUnit = $pegawai->units()->wherePivot('is_primary', true)->first()
            ?? $pegawai->units()->first();

        if (! $primaryUnit) {
            return ['l1_id' => null, 'l2_id' => null, 'has_l2' => false];
        }

        $primaryJabatan = $pegawai->jabatans()
            ->with('approverL1', 'approverL2')
            ->wherePivot('unit_sekolah_id', $primaryUnit->id)
            ->first();

        if (! $primaryJabatan) {
            return ['l1_id' => null, 'l2_id' => null, 'has_l2' => false];
        }

        $l1Id = $primaryJabatan->approver_l1_jabatan_id
            ? self::findApproverInUnit($primaryUnit->id, $primaryJabatan->approver_l1_jabatan_id)?->id
            : null;

        $l2Id = $primaryJabatan->approver_l2_jabatan_id
            ? self::findApproverInUnit($primaryUnit->id, $primaryJabatan->approver_l2_jabatan_id)?->id
            : null;

        return [
            'l1_id' => $l1Id,
            'l2_id' => $l2Id,
            'has_l2' => $primaryJabatan->approver_l2_jabatan_id !== null,
        ];
    }
}
