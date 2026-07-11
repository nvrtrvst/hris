<?php

namespace Database\Seeders;

use App\Models\Jabatan;
use Illuminate\Database\Seeder;

class JabatanSeeder extends Seeder
{
    public function run(): void
    {
        $jabatans = [
            ['nama' => 'Kepala Sekolah', 'is_guru' => false],
            ['nama' => 'Wakil Kepala Sekolah', 'is_guru' => false],
            ['nama' => 'Guru', 'is_guru' => true],
            ['nama' => 'Tata Usaha', 'is_guru' => false],
            ['nama' => 'Staff Keamanan', 'is_guru' => false],
            ['nama' => 'Staff Kebersihan', 'is_guru' => false],
        ];

        foreach ($jabatans as $jabatan) {
            Jabatan::firstOrCreate(['nama' => $jabatan['nama']], $jabatan);
        }
    }
}
