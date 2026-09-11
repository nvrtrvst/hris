<?php

namespace App\Console\Commands;

use App\Models\Presensi;
use Illuminate\Console\Command;

class BackfillTodayOpen extends Command
{
    protected $signature = 'presensi:backfill-today-open';

    protected $description = 'Backfill jam_keluar for open mengajar presensi today';

    public function handle(): int
    {
        $today = now()->toDateString();

        $open = Presensi::with('jadwal')
            ->where('tanggal', $today)
            ->whereNotNull('jadwal_id')
            ->whereNull('jam_keluar')
            ->where('is_lembur', 0)
            ->get();

        $this->info("Found {$open->count()} open presensi today.");

        if ($open->isEmpty()) {
            $this->info('Nothing to backfill.');

            return self::SUCCESS;
        }

        foreach ($open as $p) {
            $newKeluar = $p->jadwal?->jam_selesai;
            if (! $newKeluar) {
                $this->warn("  ID={$p->id}: skip (no jadwal.jam_selesai)");

                continue;
            }
            $this->line("  ID={$p->id} P={$p->pegawai_id} J={$p->jadwal_id}: jam_keluar {$p->jam_keluar} -> {$newKeluar}");
            $p->update(['jam_keluar' => $newKeluar]);
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
