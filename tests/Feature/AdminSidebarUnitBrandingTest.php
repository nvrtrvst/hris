<?php

namespace Tests\Feature;

use App\Models\UnitSekolah;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminSidebarUnitBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_unit_membagikan_branding_unit_lewat_auth_user(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $unit = UnitSekolah::create([
            'nama' => 'SMK Branding',
            'singkatan' => 'SMKB',
            'logo' => 'unit_logos/smk.png',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
            'jam_masuk_kantor' => '07:00',
            'jam_pulang_kantor' => '15:00',
        ]);

        $user = User::factory()->create([
            'role' => 'admin_unit',
            'unit_sekolah_id' => $unit->id,
        ]);
        $user->assignRole('admin_unit');

        $this->actingAs($user, 'web_admin')
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.unit_sekolah.nama', 'SMK Branding')
                ->where('auth.user.unit_sekolah.logo', 'unit_logos/smk.png'));
    }
}
