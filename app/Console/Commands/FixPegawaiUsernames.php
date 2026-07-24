<?php

namespace App\Console\Commands;

use App\Models\Pegawai;
use Illuminate\Console\Command;

class FixPegawaiUsernames extends Command
{
    protected $signature = 'pegawai:fix-usernames';

    protected $description = 'Reset username user pegawai ke nip ?: nik (plaintext)';

    public function handle()
    {
        $pegawais = Pegawai::with('user')->whereNotNull('user_id')->get();
        $fixed = 0;

        foreach ($pegawais as $pegawai) {
            if (! $pegawai->user) {
                continue;
            }

            $nik = $pegawai->nik;
            $username = $pegawai->nip ?: ($nik ?: $pegawai->user->email);

            if ($pegawai->user->username !== $username) {
                $pegawai->user->update(['username' => $username]);
                $this->line("  [{$pegawai->id}] {$pegawai->nama_lengkap}: {$pegawai->user->username} -> {$username}");
                $fixed++;
            }
        }

        $this->info("Selesai. {$fixed} username diperbaiki.");
    }
}
