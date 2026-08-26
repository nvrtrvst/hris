<?php

namespace App\Console\Commands;

use App\Helpers\ApprovalHelper;
use App\Models\PengajuanIzin;
use Illuminate\Console\Command;

class RecomputeIzinApprovers extends Command
{
    protected $signature = 'izin:recompute-approvers
                            {--dry-run : Jangan tulis perubahan, hanya laporan}';

    protected $description = 'Hitung ulang approver_l1_id/approver_l2_id untuk pengajuan izin yang masih pending.';

    public function handle(): int
    {
        $updated = 0;
        $skipped = 0;

        PengajuanIzin::where('status', 'pending')
            ->with('pegawai')
            ->chunkById(200, function ($items) use (&$updated, &$skipped) {
                foreach ($items as $p) {
                    if (! $p->pegawai) {
                        $skipped++;

                        continue;
                    }

                    $approvers = ApprovalHelper::determineApprovers($p->pegawai);
                    $changed = false;

                    if ($p->approver_l1_id !== $approvers['l1_id']) {
                        $p->approver_l1_id = $approvers['l1_id'];
                        $changed = true;
                    }
                    if ($p->approver_l2_id !== $approvers['l2_id']) {
                        $p->approver_l2_id = $approvers['l2_id'];
                        $changed = true;
                    }

                    if ($changed) {
                        $updated++;
                        if (! $this->option('dry-run')) {
                            $p->saveQuietly();
                        }
                        $this->line("  #{$p->id} -> l1=".($approvers['l1_id'] ?? 'null').' l2='.($approvers['l2_id'] ?? 'null'));
                    }
                }
            });

        if ($this->option('dry-run')) {
            $this->warn("DRY-RUN: {$updated} pengajuan akan diperbarui, {$skipped} dilewati (tanpa pegawai).");
        } else {
            $this->info("Selesai. {$updated} pengajuan diperbarui, {$skipped} dilewati (tanpa pegawai).");
        }

        return Command::SUCCESS;
    }
}
