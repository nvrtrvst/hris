<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Regresi: meta csrf-token WAJIB dirender di blade.
 *
 * Semua fetch absen (Absen.jsx, TetapPresensi.jsx, push.js) membaca token dari
 * <meta name="csrf-token">. Jika meta hilang, token = '' -> 419 CSRF mismatch
 * -> "Tidak dapat terhubung ke server" padahal server sehat.
 */
class CsrfMetaTest extends TestCase
{
    public function test_mobile_login_page_renders_csrf_token_meta(): void
    {
        $response = $this->get('/mobile/login');

        $response->assertOk();
        $response->assertSee('<meta name="csrf-token" content="', false);
    }

    public function test_admin_login_page_renders_csrf_token_meta(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('<meta name="csrf-token" content="', false);
    }
}
