<?php

namespace Tests\Feature;

use App\Services\AttestationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AttestationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        Cache::flush();
        Carbon::setTestNow('2026-10-30 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_token_valid_dan_sekali_pakai(): void
    {
        $service = app(AttestationService::class);
        $token = $service->issue();

        $this->assertTrue($service->verify($token, now()->toIso8601String())['valid']);
        $this->assertSame('attestation_used', $service->verify($token, now()->toIso8601String())['code']);
    }

    public function test_token_kedaluwarsa(): void
    {
        $service = app(AttestationService::class);
        $token = $service->issue();
        Carbon::setTestNow(now()->addMinutes(11));

        $result = $service->verify($token, now()->toIso8601String());

        $this->assertFalse($result['valid']);
        $this->assertSame('attestation_expired', $result['code']);
        $this->assertSame('retake_photo', $result['action']);
    }

    public function test_jam_perangkat_lebih_cepat(): void
    {
        $service = app(AttestationService::class);
        $token = $service->issue();
        $captured = now()->addSeconds(120)->toIso8601String();

        $result = $service->verify($token, $captured);

        $this->assertSame('device_clock_ahead', $result['code']);
        $this->assertSame('set_clock', $result['action']);
        $this->assertGreaterThan(60, $result['difference_seconds']);
    }

    public function test_jam_perangkat_lebih_lambat(): void
    {
        $service = app(AttestationService::class);
        $token = $service->issue();
        $captured = now()->subSeconds(120)->toIso8601String();

        $result = $service->verify($token, $captured);

        $this->assertSame('device_clock_behind', $result['code']);
        $this->assertSame('set_clock', $result['action']);
        $this->assertGreaterThan(60, $result['difference_seconds']);
    }

    public function test_token_tidak_ada_dan_rusak(): void
    {
        $service = app(AttestationService::class);

        $this->assertSame('attestation_missing', $service->verify(null, now()->toIso8601String())['code']);
        $this->assertSame('attestation_invalid', $service->verify('rusak', now()->toIso8601String())['code']);
    }
}
