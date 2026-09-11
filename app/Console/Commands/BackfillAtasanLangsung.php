<?php

namespace App\Console\Commands;

use App\Models\Pegawai;
use Illuminate\Console\Command;

class BackfillAtasanLangsung extends Command
{
    protected $signature = 'pegawai:backfill-atasan {--dry-run : Tampilkan laporan tanpa mengubah data}';

    protected $description = 'Wire atasan_langsung_id untuk semua pegawai yang masih NULL';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $pegawais = Pegawai::whereNull('atasan_langsung_id')
            ->with('units.jabatan')
            ->get();

        $this->info("Pegawai tanpa atasan: {$pegawais->count()}");
        $this->newLine();

        $assigned = 0;
        $skipped = 0;

        foreach ($pegawais as $p) {
            $jabatan = $p->jabatanPrimer();
            $primaryUnit = $p->units->first(fn ($u) => ! empty($u->pivot->is_primary))
                ?? $p->units->first();

            if (! $primaryUnit || ! $jabatan) {
                $skipped++;
                continue;
            }

            if (! $dryRun) {
                $p->autoAssignAtasan();
            }

            $p->refresh();
            if ($p->atasan_langsung_id) {
                $assigned++;
                $this->line("  {$p->nama_lengkap} ({$jabatan->nama}) → atasan #{$p->atasan_langsung_id}");
            } else {
                $skipped++;
                $this->line("  {$p->nama_lengkap} ({$jabatan->nama}) → tidak ada atasan ditemukan");
            }
        }

        $this->newLine();
        $this->info("Di-assign: {$assigned}");
        $this->info("Dilewati: {$skipped}");

        if ($dryRun) {
            $this->newLine();
            $this->warn('Mode dry-run — tidak ada data yang diubah.');
        }

        return self::SUCCESS;
    }
}
