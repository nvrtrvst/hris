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

        // L1 = atasan langsung (pegawai.atasan_langsung_id, diisi AtasanHierarchySeeder).
        // Rantai: guru -> Kepala Sekolah -> Ketua Yayasan.
        $l1User = optional($pegawai->atasanLangsung)->user;
        $l1Id = $l1User?->id;
        $hasL1 = $l1User !== null;

        // Fallback jabatan-config (bila atasan langsung kosong).
        if (! $l1Id) {
            $primaryJabatan = $pegawai->jabatans()
                ->with('approverL1', 'approverL2')
                ->wherePivot('unit_sekolah_id', $primaryUnit->id)
                ->first();

            if ($primaryJabatan?->approver_l1_jabatan_id) {
                $l1User = self::findApproverInUnit($primaryUnit->id, $primaryJabatan->approver_l1_jabatan_id);
                $l1Id = $l1User?->id;
                $hasL1 = $l1User !== null;
            }
        }

        // Fallback admin unit: dijadikan approver L1 (biar bisa approve) TAPI tidak
        // menandai has_l1 -> notifikasi tetap broadcast ke admin_unit + superadmin
        // (sesuai behaviour lama, menjamin superadmin tetap ke-notify).
        if (! $l1Id) {
            $adminUnit = User::where('unit_sekolah_id', $primaryUnit->id)
                ->role('admin_unit')
                ->first();
            if ($adminUnit) {
                $l1Id = $adminUnit->id;
            }
        }

        // L2 sengaja null untuk sekarang (single-level: kepsek langsung final).
        // Aktifkan nanti: $l2User = optional($pegawai->atasanLangsung?->atasanLangsung)?->user; $l2Id = $l2User?->id;
        $l2Id = null;

        return [
            'l1_id' => $l1Id,
            'l2_id' => $l2Id,
            'has_l1' => $hasL1,
            'has_l2' => false,
        ];
    }
}
