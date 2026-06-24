<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\UnitSekolah;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Superadmin
        User::firstOrCreate(
            ['email' => 'admin@yayasan.com'],
            [
                'name' => 'Super Admin Yayasan',
                'password' => Hash::make('password'),
                'role' => 'superadmin',
            ]
        );

        // Admin Unit (SMP as example)
        $smp = UnitSekolah::where('nama', 'SMP Yayasan')->first();
        if ($smp) {
            User::firstOrCreate(
                ['email' => 'admin_smp@yayasan.com'],
                [
                    'name' => 'Admin SMP Yayasan',
                    'password' => Hash::make('password'),
                    'role' => 'admin_unit',
                    'unit_sekolah_id' => $smp->id
                ]
            );
        }
    }
}
