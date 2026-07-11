<?php

namespace Database\Seeders;

use App\Models\MataPelajaran;
use Illuminate\Database\Seeder;

class MataPelajaranSeeder extends Seeder
{
    public function run(): void
    {
        $mapels = [
            'Matematika',
            'Bahasa Indonesia',
            'Bahasa Inggris',
            'Pendidikan Agama Islam',
            'Ilmu Pengetahuan Alam',
            'Ilmu Pengetahuan Sosial',
            'Pendidikan Kewarganegaraan',
            'Pendidikan Jasmani dan Rohani',
            'Seni Budaya',
            'Teknologi Informasi dan Komunikasi',
        ];

        foreach ($mapels as $mapel) {
            MataPelajaran::firstOrCreate(['nama' => $mapel]);
        }
    }
}
