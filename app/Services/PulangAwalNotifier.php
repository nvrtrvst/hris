<?php

namespace App\Services;

use App\Helpers\ApprovalHelper;
use App\Helpers\NotificationHelper;
use App\Models\Presensi;
use App\Models\User;
use App\Notifications\PulangAwalBaru;
use Illuminate\Support\Facades\Cache;

/**
 * Kabari atasan saat pegawai tercatat pulang lebih awal TANPA izin
 * (pulang_awal_diizinkan=false; yang sudah disetujui atasan tidak perlu dikabari lagi).
 * Dedup 1× per pegawai per hari (anti-spam) — kalau tidak, tiap absen keluar
 * berikutnya pada hari yang sama akan mengirim notifikasi lagi.
 */
class PulangAwalNotifier
{
    public function notify(Presensi $presensi): void
    {
        if (! $presensi->is_pulang_awal || $presensi->pulang_awal_diizinkan) {
            return;
        }

        $pegawai = $presensi->pegawai;
        if (! $pegawai) {
            return;
        }

        $tanggal = $presensi->tanggal?->toDateString() ?? now()->toDateString();
        if (! Cache::add("pulang_awal_notif:{$pegawai->id}:{$tanggal}", true, now()->endOfDay())) {
            return; // sudah dikabari hari ini
        }

        $approvers = ApprovalHelper::determineApprovers($pegawai);
        $superadmins = User::role('superadmin')->get();

        if (! empty($approvers['has_l1'])) {
            $recipients = $superadmins->push(User::find($approvers['l1_id']))->filter()->unique('id');
        } else {
            $primaryUnit = $pegawai->units()->wherePivot('is_primary', true)->first()
                ?? $pegawai->units()->first();

            $admins = User::role('admin_unit')
                ->where(function ($q) use ($primaryUnit) {
                    $q->whereNull('unit_sekolah_id')->orWhere('unit_sekolah_id', $primaryUnit?->id);
                })
                ->get();

            $recipients = $admins->merge($superadmins)->unique('id');
        }

        $notif = new PulangAwalBaru($presensi, (int) $presensi->menit_pulang_awal);
        foreach ($recipients as $target) {
            NotificationHelper::sendSafely($target, $notif);
        }
    }
}
