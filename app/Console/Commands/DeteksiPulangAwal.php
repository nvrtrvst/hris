<?php

namespace App\Console\Commands;

use App\Models\Presensi;
use App\Services\PulangAwalDetector;
use Illuminate\Console\Command;

class DeteksiPulangAwal extends Command
{
    protected $signature = 'presensi:deteksi-pulang-awal {--days=30 : Rentang hari ke belakang}';

    protected $description = 'Backfill penanda pulang awal (is_pulang_awal/menit) untuk presensi yang sudah ada.';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $detector = app(PulangAwalDetector::class);
        $total = 0;

        Presensi::with(['jadwal', 'unitSekolah'])
            ->whereNotNull('jam_keluar')
            ->whereBetween('tanggal', [now()->subDays($days)->startOfDay(), now()->endOfDay()])
            ->chunkById(200, function ($rows) use ($detector, &$total) {
                foreach ($rows as $row) {
                    $detector->apply($row);
                    // saveQuietly: jangan picu notifikasi untuk data lama.
                    $row->saveQuietly();
                    $total++;
                }
            });

        $this->info("Selesai. {$total} baris presensi diproses.");

        return self::SUCCESS;
    }
}
