<?php

namespace App\Helpers;

use App\Models\ApprovalPolicy;
use App\Models\Pegawai;
use App\Models\PengajuanIzin;
use App\Models\User;
use App\Notifications\TembusanIzin;
use App\Services\ApprovalChain;
use Illuminate\Support\Collection;
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
    public static function findHeadInUnit(?int $unitId): ?User
    {
        if (! $unitId) {
            return null;
        }

        $pegawai = Pegawai::whereHas('units', fn ($q) => $q->where('unit_sekolah.id', $unitId))
            ->whereHas('jabatans', fn ($q) => $q->whereIn('nama', self::HEAD_JABATAN_ALIASES))
            ->where('status_aktif', 'aktif')
            ->whereNotNull('user_id')
            ->first();

        return $pegawai?->user;
    }

    public static function determineApprovers(Pegawai $pegawai, string $jenis = 'izin', int $hariPengajuan = 1): array
    {
        $chain = ApprovalChain::resolve($pegawai, $jenis, now(), $hariPengajuan);

        return [
            'l1_id' => $chain['l1'],
            'l2_id' => $chain['l2'],
            'auto' => $chain['auto'],
            'has_l1' => $chain['l1'] !== null,
            'has_l2' => $chain['l2'] !== null,
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
     * Penerima tembusan izin di satu unit: user aktif yang pegawainya
     * memegang jabatan terdaftar di unit_tembusan unit tsb.
     *
     * @return Collection<int, User>
     */
    public static function tembusanIzinRecipients(int $unitId): Collection
    {
        $jabatanIds = DB::table('unit_tembusan')
            ->where('unit_sekolah_id', $unitId)
            ->pluck('jabatan_id')
            ->all();
        if ($jabatanIds === []) {
            return collect();
        }

        $userIds = DB::table('pegawai_unit')
            ->join('pegawai', 'pegawai.id', '=', 'pegawai_unit.pegawai_id')
            ->where('pegawai_unit.unit_sekolah_id', $unitId)
            ->whereIn('pegawai_unit.jabatan_id', $jabatanIds)
            ->where('pegawai.status_aktif', 'aktif')
            ->whereNotNull('pegawai.user_id')
            ->distinct()
            ->pluck('pegawai.user_id')
            ->all();

        return User::whereIn('id', $userIds)->get();
    }

    /**
     * Kirim tembusan izin (read-only, tanpa hak approve) ke pemegang jabatan
     * terdaftar di unit pegawai pengaju. $momen: diajukan|disetujui|ditolak.
     */
    public static function sendTembusanIzin(PengajuanIzin $pengajuan, string $momen, array $skipUserIds = []): void
    {
        $pegawai = $pengajuan->pegawai;
        $unit = $pegawai?->units()->wherePivot('is_primary', true)->first()
            ?? $pegawai?->units()->first();
        if (! $unit) {
            return;
        }

        $skip = array_map('intval', $skipUserIds);
        if ($pegawai?->user_id) {
            $skip[] = (int) $pegawai->user_id;
        }

        $recipients = self::tembusanIzinRecipients((int) $unit->id);

        if (ApprovalPolicy::for($pengajuan->jenis_izin)->tembusan_yayasan) {
            $recipients = $recipients->merge(self::yayasanHeadUsers());
        }

        foreach ($recipients->unique('id') as $target) {
            if (in_array((int) $target->id, $skip, true)) {
                continue;
            }

            NotificationHelper::sendSafely($target, new TembusanIzin($pengajuan, $momen));
        }
    }

    /** @return Collection<int, User> */
    public static function yayasanHeadUsers(): Collection
    {
        $jabatanIds = config('approval.yayasan_head_jabatan_ids', [24]);

        $userIds = DB::table('pegawai_unit')
            ->join('pegawai', 'pegawai.id', '=', 'pegawai_unit.pegawai_id')
            ->whereIn('pegawai_unit.jabatan_id', $jabatanIds)
            ->where('pegawai.status_aktif', 'aktif')
            ->whereNotNull('pegawai.user_id')
            ->distinct()
            ->pluck('pegawai.user_id')
            ->all();

        return User::whereIn('id', $userIds)->get();
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
