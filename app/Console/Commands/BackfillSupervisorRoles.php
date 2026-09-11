<?php

namespace App\Console\Commands;

use App\Models\Jabatan;
use App\Models\User;
use Illuminate\Console\Command;

class BackfillSupervisorRoles extends Command
{
    protected $signature = 'roles:backfill-supervisor {--dry-run : Tampilkan laporan tanpa mengubah data}';

    protected $description = 'Assign role pimpinan ke user yang jabatannya bersifat supervisor (is_supervisor)';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $supervisorJabatanIds = Jabatan::where('is_supervisor', true)->pluck('id')->toArray();

        if (empty($supervisorJabatanIds)) {
            $this->warn('Tidak ada jabatan dengan is_supervisor = true. Jalankan JabatanSeeder terlebih dahulu.');

            return self::SUCCESS;
        }

        $users = User::whereHas('pegawai', function ($q) use ($supervisorJabatanIds) {
            $q->whereHas('units', function ($q2) use ($supervisorJabatanIds) {
                $q2->whereIn('jabatan_id', $supervisorJabatanIds);
            });
        })->with('roles')->get();

        $upgraded = 0;
        $skipped = 0;

        foreach ($users as $user) {
            $currentRole = $user->roles->first()?->name;

            if ($currentRole === 'superadmin' || $currentRole === 'admin_unit') {
                $skipped++;

                continue;
            }

            if ($currentRole === 'pimpinan') {
                $skipped++;

                continue;
            }

            if (! $dryRun) {
                $user->syncRoles('pimpinan');
            }

            $upgraded++;
            $this->line("  {$user->email}: {$currentRole} → pimpinan");
        }

        $this->newLine();
        $this->info("Total user: {$users->count()}");
        $this->info("Di-upgrade: {$upgraded}");
        $this->info("Dilewati: {$skipped}");

        if ($dryRun) {
            $this->newLine();
            $this->warn('Mode dry-run — tidak ada data yang diubah.');
        }

        return self::SUCCESS;
    }
}
