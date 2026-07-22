<?php

namespace App\Console\Commands;

use App\Models\Pegawai;
use Illuminate\Console\Command;

/**
 * Backfill nik_hash untuk Pegawai existing.
 * Aman dijalankan ulang (idempotent). Skip jika hash sudah match.
 */
class HashNikPegawai extends Command
{
    protected $signature = 'pegawai:hash-nik';

    protected $description = 'Backfill SHA-256 hash kolom nik_hash dari nik terenkripsi';

    public function handle(): int
    {
        $count = 0;
        $skipped = 0;

        Pegawai::query()
            ->whereNotNull('nik')
            ->chunkById(100, function ($pegawais) use (&$count, &$skipped) {
                foreach ($pegawais as $pegawai) {
                    $raw = trim((string) $pegawai->nik);
                    if ($raw === '') {
                        $skipped++;
                        continue;
                    }
                    $expectedHash = hash('sha256', $raw);
                    if ($pegawai->nik_hash === $expectedHash) {
                        $skipped++;
                        continue;
                    }
                    $pegawai->nik_hash = $expectedHash;
                    $pegawai->save();
                    $count++;
                }
            });

        $this->info("Backfill selesai. Updated: {$count}, Skipped: {$skipped}.");

        return self::SUCCESS;
    }
}