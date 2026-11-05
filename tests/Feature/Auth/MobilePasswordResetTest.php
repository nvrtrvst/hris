<?php

namespace Tests\Feature\Auth;

use App\Models\UnitSekolah;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MobilePasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_forgot_password_screen_can_be_rendered(): void
    {
        $this->get(route('presensi.password.request'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Mobile/ForgotPassword'));
    }

    public function test_mobile_reset_link_is_sent_with_mobile_url_and_readable_email(): void
    {
        Notification::fake();

        UnitSekolah::create([
            'nama' => 'SMP Test',
            'singkatan' => 'SMPT',
            'logo' => 'unit_logos/test.png',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
        ]);
        $user = User::factory()->create();

        $this->post(route('presensi.password.email'), ['email' => $user->email])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($user) {
            $this->assertNotNull($notification->resetUrl);
            $this->assertStringContainsString('/reset-password/', $notification->resetUrl);

            $html = $notification->toMail($user)->render();

            $this->assertStringContainsString($notification->resetUrl, $html);
            $this->assertStringContainsString('Jika tombol tidak berfungsi', $html);
            $this->assertStringNotContainsString('data:image', $html);
            $this->assertStringContainsString('logo.png', $html);
            $this->assertStringContainsString('storage/unit_logos/test.png', $html);

            return true;
        });
    }

    public function test_mobile_reset_password_screen_can_be_rendered(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->get(route('presensi.password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Mobile/ResetPassword')
                ->where('token', $token)
                ->where('email', $user->email)
            );
    }

    public function test_mobile_password_can_be_reset_with_valid_token(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->post(route('presensi.password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('presensi.login'));

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
    }
}
