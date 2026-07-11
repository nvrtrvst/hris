<?php

namespace Database\Seeders;

use App\Models\UnitSekolah;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Superadmin
        $superadmin = User::firstOrCreate(
            ['email' => 'admin@yayasan.com'],
            [
                'name' => 'Super Admin Yayasan',
                'password' => Hash::make('password'),
                'role' => 'superadmin',
            ]
        );
        $superadmin->assignRole('superadmin');

        // Admin Unit (SMP as example)
        $smp = UnitSekolah::where('nama', 'SMP Yayasan')->first();
        if ($smp) {
            $adminUnit = User::firstOrCreate(
                ['email' => 'admin_smp@yayasan.com'],
                [
                    'name' => 'Admin SMP Yayasan',
                    'password' => Hash::make('password'),
                    'role' => 'admin_unit',
                    'unit_sekolah_id' => $smp->id,
                ]
            );
            $adminUnit->assignRole('admin_unit');
        }
    }
}
