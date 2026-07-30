<?php

namespace App\Console\Commands;

use App\Models\Penggajian;
use App\Models\Presensi;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CleanupFotoPresensi extends Command
{
    protected $signature = 'presensi:cleanup-foto {--dry-run : Simulasi tanpa hapus file}';

    protected $description = 'Hapus foto presensi > 3 bulan, record tetap ada';

    private const RETENSI_BULAN = 3;

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = Carbon::now()->subMonths(self::RETENSI_BULAN)->startOfDay();
        $disk = Storage::disk(config('filesystems.presensi_disk', 'presensi'));
        $filesDeleted = 0;
        $skippedPayroll = 0;
        $skippedNoFile = 0;

        $records = Presensi::where(function ($q) {
            $q->whereNotNull('foto_masuk')->orWhereNotNull('foto_keluar');
        })->where('tanggal', '<', $cutoff)->cursor();

        foreach ($records as $presensi) {
            $periodKey = Carbon::parse($presensi->tanggal)->format('m-Y');

            $payrollDraft = Penggajian::where('pegawai_id', $presensi->pegawai_id)
                ->where('periode_bulan', $periodKey)
                ->where('status', 'draft')
                ->exists();

            if ($payrollDraft) {
                $skippedPayroll++;

                continue;
            }

            $updates = [];

            foreach (['masuk', 'keluar'] as $tipe) {
                $fotoField = 'foto_'.$tipe;
                $statusField = 'foto_'.$tipe.'_status';

                $path = $presensi->$fotoField;
                if (! $path) {
                    continue;
                }

                if ($dryRun) {
                    $this->line("[DRY-RUN] Akan hapus file: {$path}");
                    $filesDeleted++;

                    continue;
                }

                if ($disk->exists($path)) {
                    $disk->delete($path);
                } else {
                    $skippedNoFile++;
                }

                $updates[$fotoField] = null;
                $updates[$statusField] = 'expired';
                $filesDeleted++;
            }

            if (! $dryRun && ! empty($updates)) {
                $presensi->update($updates);
            }
        }

        $this->info("Selesai. {$filesDeleted} file foto dibersihkan.");
        if ($skippedPayroll > 0) {
            $this->warn("{$skippedPayroll} record dilewati (payroll periode masih draft).");
        }
        if ($skippedNoFile > 0) {
            $this->line("{$skippedNoFile} file sudah tidak ditemukan di disk.");
        }

        Log::info('Cleanup foto presensi selesai', [
            'files_deleted' => $filesDeleted,
            'skipped_payroll_draft' => $skippedPayroll,
            'skipped_file_not_found' => $skippedNoFile,
            'dry_run' => $dryRun,
        ]);

        return self::SUCCESS;
    }
}
