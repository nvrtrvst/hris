<?php

namespace Database\Seeders;

use App\Models\UnitSekolah;
use Illuminate\Database\Seeder;

class UnitSekolahSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['nama' => 'LPQ', 'singkatan' => 'LPQ', 'latitude' => -6.200000, 'longitude' => 106.816666, 'radius_meter' => 50],
            ['nama' => 'TK', 'singkatan' => 'TK', 'latitude' => -6.201000, 'longitude' => 106.817000, 'radius_meter' => 50],
            ['nama' => 'SD', 'singkatan' => 'SD', 'latitude' => -6.202000, 'longitude' => 106.818000, 'radius_meter' => 50],
            ['nama' => 'SMP', 'singkatan' => 'SMP', 'latitude' => -6.203000, 'longitude' => 106.819000, 'radius_meter' => 100],
            ['nama' => 'SMA', 'singkatan' => 'SMA', 'latitude' => -6.205000, 'longitude' => 106.821000, 'radius_meter' => 100],
            ['nama' => 'SMK', 'singkatan' => 'SMK', 'latitude' => -6.204000, 'longitude' => 106.820000, 'radius_meter' => 100],
        ];

        foreach ($units as $unit) {
            UnitSekolah::firstOrCreate(['nama' => $unit['nama']], $unit);
        }
    }
}
