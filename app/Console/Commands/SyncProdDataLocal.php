<?php

namespace App\Console\Commands;

use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\PegawaiMapel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncProdDataLocal extends Command
{
    protected $signature = 'sync:prod-data {--json=scripts/prod_dump.json}';

    protected $description = 'Sync pegawai, mapel, pml from prod dump JSON to local DB';

    public function handle(): int
    {
        $path = base_path($this->option('json'));
        if (! file_exists($path)) {
            $this->error("File not found: {$path}");

            return 1;
        }

        $data = json_decode(file_get_contents($path), true);
        if (! $data) {
            $this->error('Invalid JSON');

            return 1;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        $this->info('Syncing pegawai...');
        Pegawai::query()->delete();
        foreach ($data['pegawai'] ?? [] as $row) {
            Pegawai::create(['id' => $row['id'], 'nama_lengkap' => $row['nama_lengkap']]);
        }
        $this->info('  → '.count($data['pegawai'] ?? []).' pegawai synced');

        $this->info('Syncing mapel...');
        MataPelajaran::query()->delete();
        foreach ($data['mapel'] ?? [] as $row) {
            MataPelajaran::create(['id' => $row['id'], 'nama' => $row['nama'], 'unit_sekolah_id' => $row['unit_sekolah_id'] ?? null]);
        }
        $this->info('  → '.count($data['mapel'] ?? []).' mapel synced');

        $this->info('Syncing pegawai_mapel...');
        PegawaiMapel::query()->delete();
        foreach ($data['pml'] ?? [] as $row) {
            PegawaiMapel::create(['id' => $row['id'], 'pegawai_id' => $row['pegawai_id'], 'mata_pelajaran_id' => $row['mata_pelajaran_id'], 'unit_sekolah_id' => $row['unit_sekolah_id'] ?? null]);
        }
        $this->info('  → '.count($data['pml'] ?? []).' pml synced');

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->info('Done.');

        return 0;
    }
}
