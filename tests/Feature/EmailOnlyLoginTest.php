<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EmailOnlyLoginTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'username' => 'oldusername',
        ]);
    }

    public function test_login_with_email_succeeds(): void
    {
        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);
        $response->assertRedirect();
        $this->assertAuthenticated();
    }

    public function test_login_with_username_fails(): void
    {
        $response = $this->post('/login', [
            'email' => 'oldusername',
            'password' => 'password123',
        ]);
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
