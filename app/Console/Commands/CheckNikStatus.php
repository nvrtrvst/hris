<?php

namespace App\Console\Commands;

use App\Models\Pegawai;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('nik:check')]
#[Description('Cek status NIK di tabel pegawai')]
class CheckNikStatus extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $total = DB::table('pegawai')->count();
        $cipher = DB::table('pegawai')->where('nik', 'like', 'eyJ%')->count();
        $plain = DB::table('pegawai')->whereRaw("nik IS NOT NULL AND nik NOT LIKE 'eyJ%'")->count();
        $nullNik = DB::table('pegawai')->whereNull('nik')->count();
        $hashNull = DB::table('pegawai')->whereNull('nik_hash')->count();

        $this->info("total={$total} cipher={$cipher} plain={$plain} null_nik={$nullNik} hash_null={$hashNull}");

        $p = Pegawai::first();
        if ($p) {
            $this->info('first nik_masked: '.substr($p->nik_masked ?? 'EMPTY', 0, 24));
            $this->info('first nik_hash: '.substr($p->nik_hash ?? 'EMPTY', 0, 16));
        } else {
            $this->warn('Pegawai table empty.');
        }

        return self::SUCCESS;
    }
}
