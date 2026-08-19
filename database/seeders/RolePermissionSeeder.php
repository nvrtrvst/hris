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
            'manage_jadwal', // tambah/edit/generate/hapus jadwal (admin_unit & staf yang ditunjuk)
            'view_presensi',
            'view_izin',
            'view_payroll',
            'manage_master_data', // unit sekolah, komponen gaji, dll
            'manage_users',
            'manage_roles',
            'manage_payroll',

            // Sensitive data (NIK plaintext, no_rekening, NPWP, dll)
            // Akses terbatas — superadmin only. admin_unit & pegawai melihat masked.
            'view_sensitive_data',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $roleSuperadmin = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $roleSuperadmin->syncPermissions(Permission::all());

        $roleAdminUnit = Role::firstOrCreate(['name' => 'admin_unit', 'guard_name' => 'web']);
        $roleAdminUnit->syncPermissions([
            'view_dashboard',
            'view_pegawai',
            'view_jadwal',
            'manage_jadwal',
            'view_presensi',
            'view_izin',
            // Payroll TIDAK lagi di role admin_unit — dialihkan ke jabatan
            // dgn flag `is_payroll_operator` (mis. Bendahara). Akses payroll
            // = operator jabatan (semua) + superadmin (view saja).
        ]);

        $rolePegawai = Role::firstOrCreate(['name' => 'pegawai', 'guard_name' => 'web']);
        $rolePegawai->syncPermissions([]);

        // Role PEMBACA / pengawas (read-only): Kepala Sekolah, Kepala TU, Ketua
        // Yayasan, atau peran lain (mis. HR) yang butuh memantau data bawahan
        // TANPA mengelola. Kontrak & data pegawai otomatis ter-scope ke bawahan
        // langsung via atasan_langsung_id di controller.
        $rolePimpinan = Role::firstOrCreate(['name' => 'pimpinan', 'guard_name' => 'web']);
        $rolePimpinan->syncPermissions([
            'view_dashboard',
            'view_pegawai',
            // Presensi & jadwal bawahan (pengawasan) — scope di controller via
            // trait ScopesPimpinan (hanya bawahan langsung, bukan semua unit).
            'view_presensi',
            'view_jadwal',
        ]);
    }
}
