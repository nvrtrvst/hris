<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // create permissions
        $permissions = [
            // Core
            'view_dashboard',
            'view_all_units',

            // Pegawai
            'view_pegawai',
            'view_jadwal',
            'view_presensi',
            'view_izin',
            'view_payroll',
            'manage_master_data', // unit sekolah, komponen gaji, etc
            'manage_users'
        ];

        foreach ($permissions as $permission) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permission]);
        }

        // create roles and assign created permissions
        $roleSuperadmin = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'superadmin']);
        $roleSuperadmin->givePermissionTo(\Spatie\Permission\Models\Permission::all());

        $roleAdminUnit = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin_unit']);
        $roleAdminUnit->givePermissionTo([
            'view_dashboard',
            'view_pegawai',
            'view_jadwal',
            'view_presensi',
            'view_izin',
            'view_payroll'
        ]);

        $rolePegawai = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'pegawai']);
        // pegawai doesn't have these backend permissions, they use mobile.

        // Assign superadmin role to existing superadmins
        $superadmins = \App\Models\User::where('role', 'superadmin')->get();
        foreach ($superadmins as $user) {
            $user->assignRole($roleSuperadmin);
        }

        // Assign admin_unit role to existing admin_units
        $adminUnits = \App\Models\User::where('role', 'admin_unit')->get();
        foreach ($adminUnits as $user) {
            $user->assignRole($roleAdminUnit);
        }
    }
}
