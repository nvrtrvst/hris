<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class KoreksiPresensiPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::firstOrCreate([
            'name' => 'koreksi_presensi',
            'guard_name' => 'web',
        ]);

        Role::firstOrCreate([
            'name' => 'superadmin',
            'guard_name' => 'web',
        ])->givePermissionTo('koreksi_presensi');
    }
}
