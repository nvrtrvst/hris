<?php

namespace App\Console\Commands;

use App\Models\PengajuanIzin;
use App\Services\ApprovalService;
use Illuminate\Console\Command;

class ApprovalEscalate extends Command
{
    protected $signature = 'approval:escalate';

    protected $description = 'Alihkan pengajuan yang lewat batas waktu atau penyetujunya sedang cuti.';

    public function handle(): int
    {
        $dialihkan = 0;

        PengajuanIzin::query()
            ->whereIn('approval_stage', ['pending_l1', 'pending_l2', 'pending_superadmin'])
            ->orderBy('id')
            ->chunkById(200, function ($items) use (&$dialihkan) {
                foreach ($items as $pengajuan) {
                    if (ApprovalService::escalate($pengajuan)) {
                        $dialihkan++;
                    }
                }
            });

        $this->info("Pengajuan dialihkan: {$dialihkan}");

        return Command::SUCCESS;
    }
}
