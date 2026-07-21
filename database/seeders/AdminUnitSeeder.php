<?php

namespace Database\Seeders;

use App\Models\UnitSekolah;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Assign existing admin as superadmin
        $admin = User::where('email', 'admin@yayasan.com')->first();
        if ($admin) {
            $admin->syncRoles('superadmin');
        }

        // 2. Create admin accounts for existing units
        $units = UnitSekolah::all();
        foreach ($units as $unit) {
            $email = 'admin_'.strtolower(str_replace(' ', '', $unit->nama)).'@yayasan.com';

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => 'Admin '.$unit->nama,
                    'password' => Hash::make('password'),
                    'unit_sekolah_id' => $unit->id,
                ]
            );
            $user->syncRoles('admin_unit');
        }
    }
}
