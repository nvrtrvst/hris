<?php

namespace Tests\Feature;

use Tests\TestCase;

class PortalRedirectTest extends TestCase
{
    public function test_mobile_guest_is_redirected_to_mobile_login(): void
    {
        $this->get('/mobile')->assertRedirect('/mobile/login');
    }

    public function test_admin_guest_is_redirected_to_admin_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }
}
