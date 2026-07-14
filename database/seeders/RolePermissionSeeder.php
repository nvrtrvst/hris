<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Sumber kebenaran TUNGGAL untuk permission & role Spatie.
     * Penamaan role diseragamkan lowercase: superadmin, admin_unit, pegawai.
     * Otorisasi controller/JS HARUS pakai can()/hasRole(), bukan kolom string `role`.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

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
            'manage_master_data', // unit sekolah, komponen gaji, dll
            'manage_users',
            'manage_roles',
            'manage_payroll',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $roleSuperadmin = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $roleSuperadmin->syncPermissions(Permission::all());

        $roleAdminUnit = Role::firstOrCreate(['name' => 'admin_unit', 'guard_name' => 'web']);
        $roleAdminUnit->syncPermissions([
            'view_dashboard',
            'view_pegawai',
            'view_jadwal',
            'view_presensi',
            'view_izin',
            'view_payroll',
            'manage_payroll',
        ]);

        $rolePegawai = Role::firstOrCreate(['name' => 'pegawai', 'guard_name' => 'web']);
        $rolePegawai->syncPermissions([]);
    }
}
