<?php

namespace App\Console\Commands;

use App\Helpers\NotificationHelper;
use App\Models\PengajuanIzin;
use App\Notifications\ApprovalReminder;
use App\Services\ApprovalService;
use Illuminate\Console\Command;

class ApprovalRemind extends Command
{
    protected $signature = 'approval:remind';

    protected $description = 'Kirim pengingat H-1 ke penyetuju dan pengaju.';

    public function handle(): int
    {
        $terkirim = 0;

        PengajuanIzin::query()
            ->whereIn('approval_stage', ['pending_l1', 'pending_l2', 'pending_superadmin'])
            ->whereNotNull('due_at')
            ->whereNull('reminder_sent_at')
            ->whereDate('due_at', now()->toDateString())
            ->orderBy('due_at')
            ->chunkById(200, function ($items) use (&$terkirim) {
                foreach ($items as $pengajuan) {
                    if ($pengajuan->due_at?->isPast()) {
                        continue;
                    }

                    $label = strtoupper($pengajuan->jenis_izin);
                    $batas = $pengajuan->due_at?->format('d M Y H:i');

                    foreach (ApprovalService::currentApprovers($pengajuan) as $target) {
                        NotificationHelper::sendSafely($target, new ApprovalReminder(
                            'Pengingat Persetujuan',
                            "Pengajuan {$label} {$pengajuan->pegawai?->nama_lengkap} menunggu keputusan Anda. Batas: {$batas}.",
                            route('pengajuan-izin.index'),
                        ));
                    }

                    NotificationHelper::sendSafely($pengajuan->pegawai?->user, new ApprovalReminder(
                        'Status Pengajuan',
                        "Pengajuan {$label} Anda masih menunggu persetujuan. Batas: {$batas}.",
                        route('presensi.izin.index'),
                    ));

                    $pengajuan->reminder_sent_at = now();
                    $pengajuan->save();
                    $terkirim++;
                }
            });

        $this->info("Pengingat terkirim: {$terkirim}");

        return Command::SUCCESS;
    }
}
