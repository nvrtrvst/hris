<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $units = [
            ['nama' => 'LPQ', 'singkatan' => 'LPQ', 'latitude' => -6.200000, 'longitude' => 106.816666, 'radius_meter' => 50],
            ['nama' => 'TK', 'singkatan' => 'TK', 'latitude' => -6.201000, 'longitude' => 106.817000, 'radius_meter' => 50],
            ['nama' => 'SD', 'singkatan' => 'SD', 'latitude' => -6.202000, 'longitude' => 106.818000, 'radius_meter' => 50],
            ['nama' => 'SMP', 'singkatan' => 'SMP', 'latitude' => -6.203000, 'longitude' => 106.819000, 'radius_meter' => 100],
            ['nama' => 'SMK', 'singkatan' => 'SMK', 'latitude' => -6.204000, 'longitude' => 106.820000, 'radius_meter' => 100],
        ];

        foreach ($units as $unit) {
            \App\Models\UnitSekolah::firstOrCreate(['nama' => $unit['nama']], $unit);
        }

        $jabatans = [
            ['nama' => 'Kepala Sekolah', 'is_guru' => true],
            ['nama' => 'Wakil Kepala Sekolah', 'is_guru' => true],
            ['nama' => 'Guru Kelas', 'is_guru' => true],
            ['nama' => 'Guru Mata Pelajaran', 'is_guru' => true],
            ['nama' => 'Staff Tata Usaha', 'is_guru' => false],
            ['nama' => 'Satpam', 'is_guru' => false],
            ['nama' => 'Petugas Kebersihan', 'is_guru' => false],
        ];

        foreach ($jabatans as $jabatan) {
            \App\Models\Jabatan::firstOrCreate(['nama' => $jabatan['nama']], $jabatan);
        }
    }
}
