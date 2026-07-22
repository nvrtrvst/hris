<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('nik:check')]
#[Description('Cek status NIK di tabel pegawai')]
class CheckNikStatus extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $total = \Illuminate\Support\Facades\DB::table('pegawai')->count();
        $cipher = \Illuminate\Support\Facades\DB::table('pegawai')->where('nik', 'like', 'eyJ%')->count();
        $plain = \Illuminate\Support\Facades\DB::table('pegawai')->whereRaw("nik IS NOT NULL AND nik NOT LIKE 'eyJ%'")->count();
        $nullNik = \Illuminate\Support\Facades\DB::table('pegawai')->whereNull('nik')->count();
        $hashNull = \Illuminate\Support\Facades\DB::table('pegawai')->whereNull('nik_hash')->count();

        $this->info("total={$total} cipher={$cipher} plain={$plain} null_nik={$nullNik} hash_null={$hashNull}");

        $p = \App\Models\Pegawai::first();
        if ($p) {
            $this->info('first nik_masked: '.substr($p->nik_masked ?? 'EMPTY', 0, 24));
            $this->info('first nik_hash: '.substr($p->nik_hash ?? 'EMPTY', 0, 16));
        } else {
            $this->warn('Pegawai table empty.');
        }

        return self::SUCCESS;
    }
}
