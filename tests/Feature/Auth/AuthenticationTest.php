<?php

namespace Tests\Feature\Auth;

use App\Models\UnitSekolah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_login_lembaga_branding_dan_urutan(): void
    {
        $yayasan = UnitSekolah::create([
            'nama' => 'Yayasan Nuurul Muttaqiin',
            'singkatan' => 'YAYASAN',
            'logo' => 'unit_logos/yayasan.png',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'jam_masuk_kantor' => '07:00',
        ]);

        foreach ([
            ['SMK Nuurul Muttaqiin', 'SMK'],
            ['LPQ', 'LPQ'],
            ['TK', 'TK'],
            ['SD Plus Nuurul Muttaqiin', 'SD'],
            ['SMP Plus Nuurul Muttaqiin', 'SMP'],
        ] as [$nama, $singkatan]) {
            UnitSekolah::create([
                'nama' => $nama,
                'singkatan' => $singkatan,
                'latitude' => -6.2,
                'longitude' => 106.8,
                'radius_meter' => 100,
                'jam_masuk_kantor' => '07:00',
            ]);
        }

        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Login')
                ->where('brandLogo', $yayasan->logo_url)
                ->has('lembaga', 5)
                ->where('lembaga.0.singkatan', 'LPQ')
                ->where('lembaga.1.singkatan', 'TK')
                ->where('lembaga.2.singkatan', 'SD')
                ->where('lembaga.3.singkatan', 'SMP')
                ->where('lembaga.4.singkatan', 'SMK'));
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'login' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
