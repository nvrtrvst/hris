<?php

namespace App\Console\Commands;

use App\Models\ApprovalPolicy;
use App\Models\PengajuanIzin;
use App\Services\ApprovalChain;
use App\Services\IzinPresensiService;
use Illuminate\Console\Command;

class RecomputeIzinApprovers extends Command
{
    protected $signature = 'izin:recompute-approvers
                            {--dry-run : Jangan tulis perubahan, hanya laporan}';

    protected $description = 'Hitung ulang penyetuju dan batas waktu untuk pengajuan izin yang masih pending.';

    public function handle(): int
    {
        $updated = 0;
        $skipped = 0;
        $dry = (bool) $this->option('dry-run');

        PengajuanIzin::where('status', 'pending')
            ->with('pegawai.units')
            ->chunkById(200, function ($items) use (&$updated, &$skipped, $dry) {
                foreach ($items as $pengajuan) {
                    $pegawai = $pengajuan->pegawai;

                    if (! $pegawai) {
                        $skipped++;

                        continue;
                    }

                    $policy = ApprovalPolicy::for($pengajuan->jenis_izin);
                    $hari = (int) $pengajuan->tanggal_mulai->diffInDays($pengajuan->tanggal_selesai) + 1;
                    $chain = ApprovalChain::resolve($pegawai, $policy->jenis, now(), $hari);

                    $berubah = (int) $pengajuan->approver_l1_id !== (int) ($chain['l1'] ?? 0)
                        || (int) $pengajuan->approver_l2_id !== (int) ($chain['l2'] ?? 0);

                    if (! $berubah && ! $chain['auto']) {
                        continue;
                    }

                    $updated++;
                    $this->line("  #{$pengajuan->id} -> l1=".($chain['l1'] ?? 'null').' l2='.($chain['l2'] ?? 'null').($chain['auto'] ? ' auto' : ''));

                    if ($dry) {
                        continue;
                    }

                    if ($chain['auto']) {
                        $pengajuan->forceFill([
                            'approver_l1_id' => null,
                            'approver_l2_id' => null,
                            'approval_stage' => 'approved',
                            'status' => 'disetujui',
                            'approved_at_l1' => now(),
                            'approved_at_l2' => now(),
                            'catatan_approval' => 'Tanpa persetujuan (puncak organisasi).',
                        ])->save();

                        IzinPresensiService::generate($pengajuan);

                        continue;
                    }

                    $pengajuan->forceFill([
                        'approver_l1_id' => $chain['l1'],
                        'approver_l2_id' => $chain['l2'],
                        'approval_stage' => $chain['l1'] === null ? 'pending_superadmin' : $pengajuan->approval_stage,
                        'due_at' => $pengajuan->due_at ?? ApprovalChain::dueAt(now(), $policy->sla_days, ApprovalChain::unitOf($pegawai)),
                    ])->save();
                }
            });

        $this->{$dry ? 'warn' : 'info'}(
            $dry
                ? "DRY-RUN: {$updated} pengajuan akan diperbarui, {$skipped} dilewati."
                : "Selesai. {$updated} pengajuan diperbarui, {$skipped} dilewati."
        );

        return Command::SUCCESS;
    }
}
