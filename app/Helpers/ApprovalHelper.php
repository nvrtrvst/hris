<?php

namespace App\Helpers;

use App\Models\Pegawai;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ApprovalHelper
{
    // Jabatan yang dianggap pimpinan/kepala unit (untuk live lookup approver L1).
    public const HEAD_JABATAN_ALIASES = [
        'Kepala Sekolah',
        'Kepala Madrasah',
        'Kepala',
        'Pimpinan',
        'Ketua Yayasan',
        'Ketua',
    ];

    /**
     * Cari user Kepala Sekolah (pimpinan) unit secara live.
     * Robust thd AtasanHierarchySeeder yang mungkin belum jalan.
     */
    public static function findHeadInUnit(int $unitId): ?User
    {
        $pegawai = Pegawai::whereHas('units', fn ($q) => $q->where('unit_sekolah.id', $unitId))
            ->whereHas('jabatans', fn ($q) => $q->whereIn('nama', self::HEAD_JABATAN_ALIASES))
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
        // Fallback: Kepala Sekolah unit tsb (live lookup, robust thd seeder belum jalan).
        $l1User = optional($pegawai->atasanLangsung)->user;
        $l1Id = $l1User?->id;
        $hasL1 = $l1User !== null;

        if (! $l1Id) {
            $l1User = self::findHeadInUnit($primaryUnit->id);
            $l1Id = $l1User?->id;
            $hasL1 = $l1User !== null;
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

    /**
     * Apakah user adalah pimpinan (kepala) unit tertentu?
     * True bila: superadmin, admin_unit di unit tsb, atau pegawainya berjabatan head di unit tsb.
     */
    public static function isUnitHead(User $user, ?int $unitId): bool
    {
        if (! $unitId) {
            return false;
        }

        if ($user->hasRole('superadmin')) {
            return true;
        }

        if ($user->hasRole('admin_unit') && $user->unit_sekolah_id === $unitId) {
            return true;
        }

        $peg = $user->pegawai;
        if (! $peg) {
            return false;
        }

        return DB::table('pegawai_unit')
            ->join('jabatan', 'jabatan.id', '=', 'pegawai_unit.jabatan_id')
            ->where('pegawai_unit.pegawai_id', $peg->id)
            ->where('pegawai_unit.unit_sekolah_id', $unitId)
            ->whereIn('jabatan.nama', self::HEAD_JABATAN_ALIASES)
            ->exists();
    }

    /**
     * Unit IDs tempat user bertindak sebagai pimpinan (kepala/admin_unit).
     */
    public static function headUnitIds(User $user): array
    {
        $ids = [];

        if ($user->hasRole('admin_unit') && $user->unit_sekolah_id) {
            $ids[] = $user->unit_sekolah_id;
        }

        $peg = $user->pegawai;
        if ($peg) {
            $units = DB::table('pegawai_unit')
                ->join('jabatan', 'jabatan.id', '=', 'pegawai_unit.jabatan_id')
                ->where('pegawai_unit.pegawai_id', $peg->id)
                ->whereIn('jabatan.nama', self::HEAD_JABATAN_ALIASES)
                ->pluck('pegawai_unit.unit_sekolah_id')
                ->toArray();
            $ids = array_merge($ids, $units);
        }

        return array_values(array_unique($ids));
    }
}
