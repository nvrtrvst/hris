<?php

namespace Tests\Feature;

use Tests\TestCase;

class MobileClassIntegrationTest extends TestCase
{
    public function test_guest_cannot_lookup_classes(): void
    {
        $this->get('/mobile/jadwal/kelas')->assertRedirect();
    }
}
