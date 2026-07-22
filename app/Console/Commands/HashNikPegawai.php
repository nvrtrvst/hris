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
        $errored = 0;

        Pegawai::query()
            ->whereNotNull('nik')
            ->chunkById(100, function ($pegawais) use (&$count, &$skipped, &$errored) {
                foreach ($pegawais as $pegawai) {
                    // Baca RAW ciphertext dari underlying attributes, bukan via
                    // accessor (auto-decrypt) yang akan throw DecryptException
                    // bila nilai legacy / korup.
                    $cipher = $pegawai->getRawOriginal('nik');

                    if ($cipher === null || trim((string) $cipher) === '') {
                        $skipped++;
                        continue;
                    }

                    try {
                        $plain = \Illuminate\Support\Facades\Crypt::decryptString($cipher);
                    } catch (\Throwable) {
                        // Legacy plaintext atau data korup. Skip — biarkan nanti
                        // diurus oleh migration cleanup / admin manual.
                        $errored++;
                        continue;
                    }

                    $plain = trim($plain);
                    if ($plain === '') {
                        $skipped++;
                        continue;
                    }

                    $expectedHash = hash('sha256', $plain);
                    if ($pegawai->nik_hash === $expectedHash) {
                        $skipped++;
                        continue;
                    }
                    $pegawai->nik_hash = $expectedHash;
                    $pegawai->saveQuietly();
                    $count++;
                }
            });

        $this->info("Backfill selesai. Updated: {$count}, Skipped: {$skipped}, Errored: {$errored}.");

        return self::SUCCESS;
    }
}