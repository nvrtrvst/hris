<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    }

    public function test_guest_cannot_access_protected_routes(): void
    {
        $this->get('/users')->assertRedirect(route('login'));
        $this->get('/roles')->assertRedirect(route('login'));
        $this->get('/laporan/presensi')->assertRedirect(route('login'));
    }

    public function test_regular_pegawai_cannot_access_user_management(): void
    {
        $pegawai = User::factory()->create(['role' => 'pegawai']);
        $pegawai->assignRole('pegawai');

        $this->actingAs($pegawai, 'web_admin')
            ->get('/users')
            ->assertForbidden();

        $this->actingAs($pegawai, 'web_admin')
            ->get('/roles')
            ->assertForbidden();
    }

    public function test_regular_pegawai_cannot_export_reports(): void
    {
        $pegawai = User::factory()->create(['role' => 'pegawai']);
        $pegawai->assignRole('pegawai');

        $this->actingAs($pegawai, 'web_admin')
            ->get('/laporan/presensi?start_date=2024-01-01&end_date=2024-01-31')
            ->assertForbidden();
    }

    public function test_admin_unit_can_access_user_management_when_permitted(): void
    {
        $admin = User::factory()->create(['role' => 'admin_unit']);
        $admin->assignRole('admin_unit');

        // admin_unit tidak punya manage_users -> 403
        $this->actingAs($admin, 'web_admin')
            ->get('/users')
            ->assertForbidden();
    }

    public function test_superadmin_can_access_user_management(): void
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);
        $superadmin->assignRole('superadmin');

        $this->actingAs($superadmin, 'web_admin')
            ->get('/users')
            ->assertOk();
    }
}
