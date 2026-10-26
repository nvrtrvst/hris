<?php

namespace Database\Seeders;

use App\Models\ApprovalPolicy;
use Illuminate\Database\Seeder;

class ApprovalPolicySeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['jenis' => 'izin', 'levels' => 1, 'sla_days' => 2],
            ['jenis' => 'cuti', 'levels' => 2, 'second_level_min_days' => 3, 'sla_days' => 2],
            ['jenis' => 'koreksi', 'levels' => 1, 'sla_days' => 1],
            ['jenis' => 'pulang_cepat', 'levels' => 1, 'sla_days' => 1],
            ['jenis' => 'lembur', 'levels' => 1, 'sla_days' => 1],
            ['jenis' => 'tugas_luar', 'levels' => 1, 'sla_days' => 1],
        ];

        foreach ($defaults as $row) {
            ApprovalPolicy::updateOrCreate(
                ['jenis' => $row['jenis']],
                $row + ['tembusan_yayasan' => true, 'is_active' => true],
            );
        }
    }
}
