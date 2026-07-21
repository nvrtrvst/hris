<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Root path enters desktop portal redirect chain.
     */
    public function test_root_redirects_to_dashboard(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('dashboard'));
    }
}
