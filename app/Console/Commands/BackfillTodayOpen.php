<?php

namespace App\Console\Commands;

use App\Models\Presensi;
use Illuminate\Console\Command;

class BackfillTodayOpen extends Command
{
    protected $signature = 'presensi:backfill-today-open';

    protected $description = 'Backfill jam_masuk/jam_keluar mengajar presensi hari ini dari jadwal';

    public function handle(): int
    {
        $today = now()->toDateString();

        $rows = Presensi::with('jadwal')
            ->where('tanggal', $today)
            ->whereNotNull('jadwal_id')
            ->where('is_lembur', 0)
            ->where('tipe_presensi', 'mengajar')
            ->where(function ($q) {
                $q->whereNull('jam_keluar')->orWhereNull('jam_masuk');
            })
            ->get();

        $this->info("Found {$rows->count()} incomplete mengajar presensi today.");

        if ($rows->isEmpty()) {
            $this->info('Nothing to backfill.');

            return self::SUCCESS;
        }

        $fixed = 0;
        foreach ($rows as $p) {
            $jadwal = $p->jadwal;
            if (! $jadwal?->jam_selesai) {
                $this->warn("  ID={$p->id}: skip (no jadwal)");

                continue;
            }

            $updates = [];
            // Cover rows (tanpa jam_masuk): isi jam_masuk = jadwal.jam_mulai.
            // Anchor rows (jam_masuk aktual user): pertahankan jam_masuk.
            if (! $p->jam_masuk && $p->keterangan && str_contains($p->keterangan, 'auto-cover')) {
                $updates['jam_masuk'] = $jadwal->jam_mulai;
            }
            if (! $p->jam_keluar) {
                $updates['jam_keluar'] = $jadwal->jam_selesai;
            }

            if (! $updates) {
                continue;
            }

            $this->line("  ID={$p->id} P={$p->pegawai_id} J={$p->jadwal_id}: ".implode(', ', array_keys($updates)));
            $p->update($updates);
            $fixed++;
        }

        $this->info("Done. {$fixed} rows fixed.");

        return self::SUCCESS;
    }
}
