<?php

namespace App\Console\Commands;

use App\Models\Pegawai;
use Illuminate\Console\Command;

class PegawaiBackfillPendidikan extends Command
{
    protected $signature = 'pegawai:backfill-pendidikan {--dry-run : Tampilkan laporan tanpa mengubah data}';

    protected $description = 'Laporkan pegawai yang kolom pendidikan/SK masih kosong';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $fields = [
            'pendidikan_tahun_lulus',
            'pendidikan_asal_sekolah',
            'sk_nomor',
            'sk_tanggal',
        ];

        $emptyCounts = [];
        foreach ($fields as $field) {
            $emptyCounts[$field] = Pegawai::whereNull($field)->orWhere($field, '')->count();
        }

        $total = Pegawai::count();

        $this->info("Total pegawai: {$total}");
        $this->newLine();
        $this->info('Kolom kosong:');

        foreach ($emptyCounts as $field => $count) {
            $pct = $total > 0 ? round($count / $total * 100, 1) : 0;
            $this->line("  {$field}: {$count}/{$total} ({$pct}%)");
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn('Mode dry-run — tidak ada data yang diubah.');
        }

        return self::SUCCESS;
    }
}
