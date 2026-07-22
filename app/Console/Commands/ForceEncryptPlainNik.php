<?php

namespace App\Console\Commands;

use App\Models\Pegawai;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Force-encrypt baris NIK yang masih plaintext digit-only (legacy).
 * Aman di-rerun. Setelah migrasi 020000, biasanya 0 row tersisa
 * kecuali ada seeder / manual insert yg nyisipin plaintext.
 *
 * ponytail: re-run safe, idempotent, skip yg sudah ciphertext.
 */
class ForceEncryptPlainNik extends Command
{
    protected $signature = 'pegawai:force-encrypt-plain';

    protected $description = 'Encrypt baris NIK legacy plaintext yang masih ada';

    public function handle(): int
    {
        $count = 0;
        $skipped = 0;

        DB::table('pegawai')
            ->whereNotNull('nik')
            ->orderBy('id')
            ->chunkById(100, function ($rows) use (&$count, &$skipped) {
                foreach ($rows as $row) {
                    $raw = (string) $row->nik;

                    if (str_starts_with($raw, 'eyJ')) {
                        $skipped++;
                        continue;
                    }

                    if (! preg_match('/^\d{8,}$/', $raw)) {
                        continue;
                    }

                    $cipher = Crypt::encryptString($raw);
                    $hash = hash('sha256', $raw);

                    DB::table('pegawai')
                        ->where('id', $row->id)
                        ->update(['nik' => $cipher, 'nik_hash' => $hash]);

                    $count++;
                }
            });

        $this->info("Force-encrypt selesai. Encrypted: {$count}, Skipped (ciphertext): {$skipped}.");

        return self::SUCCESS;
    }
}