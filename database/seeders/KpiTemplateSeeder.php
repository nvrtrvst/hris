<?php

namespace Database\Seeders;

use App\Models\Jabatan;
use App\Services\KpiTemplateDefaults;
use Illuminate\Database\Seeder;

class KpiTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = app(KpiTemplateDefaults::class);
        foreach (Jabatan::orderBy('nama')->get() as $jabatan) {
            $defaults->ensureForJabatan($jabatan);
        }
    }
}
