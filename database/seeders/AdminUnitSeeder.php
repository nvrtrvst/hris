<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdminUnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Update existing admin to superadmin
        $admin = \App\Models\User::where('email', 'admin@yayasan.com')->first();
        if ($admin) {
            $admin->update(['role' => 'superadmin']);
        }

        // 2. Create admin accounts for existing units
        $units = \App\Models\UnitSekolah::all();
        foreach ($units as $unit) {
            $email = 'admin_' . strtolower(str_replace(' ', '', $unit->nama)) . '@yayasan.com';
            
            \App\Models\User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => 'Admin ' . $unit->nama,
                    'password' => \Illuminate\Support\Facades\Hash::make('password'),
                    'role' => 'admin_unit',
                    'unit_sekolah_id' => $unit->id,
                ]
            );
        }
    }
}
