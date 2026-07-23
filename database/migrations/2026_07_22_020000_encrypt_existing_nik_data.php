<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Migrasi DATA NIK plaintext legacy → ciphertext Laravel encrypted cast.
 *
 * Kolom nik sudah ber-TEXT (dari migrasi sebelumnya). Data existing yang
 * plaintext digit-only (hasil Legacy tanpa encrypt) perlu di-encrypt ulang
 * agar cast `encrypted` di Eloquent dapat membaca tanpa DecryptException.
 *
 * Aman di-rerun: skip baris yang sudah ciphertext (prefix `eyJ`).
 */
return new class extends Migration
{
    public function up(): void
    {
        $updated = 0;
        $skipped = 0;
        $errored = 0;

        DB::table('pegawai')
            ->whereNotNull('nik')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use (&$updated, &$skipped, &$errored) {
                foreach ($rows as $row) {
                    $raw = (string) $row->nik;

                    // Skip kalau sudah ciphertext Laravel (base64 JSON: {"iv":...}).
                    if (str_starts_with($raw, 'eyJ')) {
                        $skipped++;

                        continue;
                    }

                    // Skip kalau bukan digit polos (data korup atau format lain).
                    if (! preg_match('/^\d{8,}$/', $raw)) {
                        $errored++;

                        continue;
                    }

                    try {
                        $cipher = Crypt::encryptString($raw);
                        $hash = hash('sha256', $raw);
                    } catch (Throwable) {
                        $errored++;

                        continue;
                    }

                    DB::table('pegawai')
                        ->where('id', $row->id)
                        ->update([
                            'nik' => $cipher,
                            'nik_hash' => $hash,
                        ]);
                    $updated++;
                }
            });

        // Log ke migration output; tidak dipake di runtime tapi berguna untuk audit.
        error_log(sprintf('[EncryptLegacyNik] updated=%d skipped=%d errored=%d', $updated, $skipped, $errored));
    }

    public function down(): void
    {
        // Tidak reversible aman — tidak bisa balik dari ciphertext tanpa key.
        // Admin harus restore dari backup jika perlu.
    }
};
