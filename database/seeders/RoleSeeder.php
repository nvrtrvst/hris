<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Buat roles
        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web_admin']);
        $hrPusat = Role::firstOrCreate(['name' => 'HR Pusat', 'guard_name' => 'web_admin']);
        $adminUnit = Role::firstOrCreate(['name' => 'Admin Unit', 'guard_name' => 'web_admin']);
        $staff = Role::firstOrCreate(['name' => 'Staff', 'guard_name' => 'web_admin']);
        $staffMobile = Role::firstOrCreate(['name' => 'Staff', 'guard_name' => 'web_mobile']);

        // Set Super Admin untuk admin@yayasan.com
        $adminUser = User::where('email', 'admin@yayasan.com')->first();
        if ($adminUser) {
            setPermissionsTeamId(1); // Gunakan unit_sekolah_id = 1 sebagai default
            $adminUser->assignRole($superAdmin);
        }

        // Buat dummy HR Pusat
        $hrUser = User::firstOrCreate(
            ['email' => 'hr@yayasan.com'],
            [
                'name' => 'HR Manager',
                'password' => Hash::make('password'),
            ]
        );
        setPermissionsTeamId(1);
        $hrUser->assignRole($hrPusat);

        // Buat dummy Admin Unit
        $unitUser = User::firstOrCreate(
            ['email' => 'unit@yayasan.com'],
            [
                'name' => 'Admin Sekolah',
                'password' => Hash::make('password'),
            ]
        );
        setPermissionsTeamId(2); // Misal unit_sekolah_id = 2
        $unitUser->assignRole($adminUnit);
    }
}
