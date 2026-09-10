<?php

namespace Database\Seeders;

use App\Models\PayrollReferenceType;
use Illuminate\Database\Seeder;

class PayrollReferenceTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'kode' => 'pendidikan',
                'nama' => 'Pendidikan Terakhir',
                'source_field' => 'pendidikan_terakhir',
                'is_active' => true,
            ],
            [
                'kode' => 'status_kepegawaian',
                'nama' => 'Status Kepegawaian',
                'source_field' => 'status_kepegawaian',
                'is_active' => true,
            ],
        ];

        foreach ($types as $type) {
            PayrollReferenceType::firstOrCreate(['kode' => $type['kode']], $type);
        }
    }
}
