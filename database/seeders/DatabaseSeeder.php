<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // Master / akses
            RolePermissionSeeder::class,
            AdminUnitSeeder::class,

            // Master data
            UnitSekolahSeeder::class,
            JabatanSeeder::class,
            MataPelajaranSeeder::class,
            PayrollReferenceTypeSeeder::class,
            KomponenGajiSeeder::class,

            // Pegawai
            MassivePegawaiSeeder::class,
            NonGuruPegawaiSeeder::class,
            PegawaiSeeder::class,

            // Relasi pegawai
            AtasanHierarchySeeder::class,

            // Data tambahan / demo
            MasterDataSeeder::class,
            UserSeeder::class,
            DemoGuruJadwalSeeder::class,
            BulkPresensiSeeder::class,
            DummyDataSeeder::class,
        ]);
    }
}
