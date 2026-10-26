<?php

namespace App\Services;

use App\Helpers\NotificationHelper;
use App\Models\ApprovalPolicy;
use App\Models\Pegawai;
use App\Models\PengajuanIzin;
use App\Models\User;
use App\Notifications\ApprovalReminder;
use Illuminate\Support\Collection;

class ApprovalService
{
    /**
     * Naikkan tahap bila lewat batas waktu atau penyetuju sedang cuti.
     */
    public static function escalate(PengajuanIzin $pengajuan): bool
    {
        if (! in_array($pengajuan->approval_stage, ['pending_l1', 'pending_l2', 'pending_superadmin'], true)) {
            return false;
        }

        $approverId = $pengajuan->approval_stage === 'pending_l1'
            ? $pengajuan->approver_l1_id
            : $pengajuan->approver_l2_id;

        $lewatBatas = $pengajuan->due_at !== null && $pengajuan->due_at->isPast();
        $penyetujuCuti = $approverId !== null && self::sedangCuti((int) $approverId);

        if (! $lewatBatas && ! $penyetujuCuti) {
            return false;
        }

        if ($pengajuan->approval_stage === 'pending_l1') {
            $l2 = $pengajuan->approver_l2_id;

            if ($l2 && ! self::isYayasanHead((int) $l2)) {
                $pengajuan->approval_stage = 'pending_l2';
            } else {
                $pengajuan->approval_stage = 'pending_superadmin';
            }
        } elseif ($pengajuan->approval_stage === 'pending_l2') {
            $pengajuan->approval_stage = 'pending_superadmin';
        } else {
            // Sudah di antrean Superadmin: tidak ada tahap berikutnya.
            return false;
        }

        $pengajuan->escalated_at = now();
        $pengajuan->reminder_sent_at = null;
        $pengajuan->due_at = ApprovalChain::dueAt(
            now(),
            ApprovalPolicy::for($pengajuan->jenis_izin)->sla_days,
            $pengajuan->pegawai ? ApprovalChain::unitOf($pengajuan->pegawai) : null,
        );
        $pengajuan->save();

        self::notifyEscalation($pengajuan);

        return true;
    }

    public static function currentApprovers(PengajuanIzin $pengajuan): Collection
    {
        return match ($pengajuan->approval_stage) {
            'pending_l1' => User::whereKey($pengajuan->approver_l1_id)->get(),
            'pending_l2' => User::whereKey($pengajuan->approver_l2_id)->get(),
            'pending_superadmin' => User::role('superadmin')->get(),
            default => collect(),
        };
    }

    public static function notifyEscalation(PengajuanIzin $pengajuan): void
    {
        $label = strtoupper($pengajuan->jenis_izin);
        $pesan = $pengajuan->approval_stage === 'pending_superadmin'
            ? "Pengajuan {$label} {$pengajuan->pegawai?->nama_lengkap} menunggu keputusan Superadmin."
            : "Pengajuan {$label} {$pengajuan->pegawai?->nama_lengkap} dialihkan ke Anda karena penyetuju sebelumnya tidak merespons.";

        foreach (self::currentApprovers($pengajuan) as $target) {
            NotificationHelper::sendSafely($target, new ApprovalReminder(
                'Pengajuan Dialihkan',
                $pesan,
                route('pengajuan-izin.index'),
            ));
        }

        NotificationHelper::sendSafely($pengajuan->pegawai?->user, new ApprovalReminder(
            'Status Pengajuan',
            "Pengajuan {$label} Anda dialihkan ke penyetuju berikutnya.",
            route('presensi.izin.index'),
        ));
    }

    private static function sedangCuti(int $userId): bool
    {
        $pegawaiId = Pegawai::where('user_id', $userId)->value('id');

        if (! $pegawaiId) {
            return false;
        }

        return PengajuanIzin::query()
            ->where('pegawai_id', $pegawaiId)
            ->where('status', 'disetujui')
            ->whereDate('tanggal_mulai', '<=', now())
            ->whereDate('tanggal_selesai', '>=', now())
            ->exists();
    }

    private static function isYayasanHead(int $userId): bool
    {
        $jabatanIds = config('approval.yayasan_head_jabatan_ids', [24]);

        return Pegawai::where('user_id', $userId)
            ->whereHas('jabatans', fn ($q) => $q->whereIn('jabatan.id', $jabatanIds))
            ->exists();
    }
}
