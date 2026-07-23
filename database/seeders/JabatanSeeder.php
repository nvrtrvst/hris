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
            ['nama' => 'Guru Mata Pelajaran', 'is_guru' => true],
            ['nama' => 'Guru Kelas', 'is_guru' => true],
            ['nama' => 'Guru BK', 'is_guru' => true],
            ['nama' => 'Wali Kelas', 'is_guru' => false],
            ['nama' => 'Tenaga Administrasi', 'is_guru' => false],
            ['nama' => 'Operator / Pranata Komputer', 'is_guru' => false],
            ['nama' => 'Pustakawan', 'is_guru' => false],
            ['nama' => 'Satpam', 'is_guru' => false],
            ['nama' => 'Petugas Kebersihan', 'is_guru' => false],
        ];

        foreach ($jabatans as $jabatan) {
            Jabatan::firstOrCreate(['nama' => $jabatan['nama']], $jabatan);
        }
    }
}
