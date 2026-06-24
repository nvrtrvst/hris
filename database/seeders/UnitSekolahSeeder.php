<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\UnitSekolah;

class UnitSekolahSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['nama' => 'TK Yayasan', 'singkatan' => 'TK'],
            ['nama' => 'SD Yayasan', 'singkatan' => 'SD'],
            ['nama' => 'SMP Yayasan', 'singkatan' => 'SMP'],
            ['nama' => 'SMA Yayasan', 'singkatan' => 'SMA'],
            ['nama' => 'SMK Yayasan', 'singkatan' => 'SMK'],
        ];

        foreach ($units as $unit) {
            UnitSekolah::firstOrCreate(['nama' => $unit['nama']], $unit);
        }
    }
}
