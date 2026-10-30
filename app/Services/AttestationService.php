<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Server-side timestamp attestation untuk anti-spoof presensi.
 *
 * Setiap kali halaman absen di-load, issue() membuat token ter-enkripsi
 * berisi issued_at + nonce. Saat submit, verify() memeriksa:
 * 1. HMAC/Crypt integritas token (tidak di-tamper)
 * 2. Nonce belum dipakai (anti-replay, cache 10 menit)
 * 3. captured_at tidak sebelum issued_at (cegak kloning request masa lalu)
 * 4. captured_at tidak lebih dari 60 detik ke depan (cegak jam HP dimajukan)
 */
class AttestationService
{
    private const CACHE_PREFIX = 'attestation_nonce_';

    private const USED_PREFIX = 'attestation_used_';

    private const TOKEN_TTL = 600; // 10 menit

    private const MAX_CLOCK_SKEW = 60;

    /**
     * Terbitkan token untuk halaman absen.
     */
    public function issue(): string
    {
        $payload = [
            'issued_at' => Carbon::now()->timestamp,
            'nonce' => Str::random(32),
        ];

        // Nonce disimpan di cache untuk cek replay
        Cache::put(self::CACHE_PREFIX.$payload['nonce'], true, self::TOKEN_TTL);

        // Enkripsi payload — APP_KEY sebagai signing key
        return Crypt::encryptString(json_encode($payload));
    }

    /**
     * Verifikasi token. Return true jika valid.
     *
     * @param  string|null  $token  Token dari client
     * @param  string|null  $capturedAt  Client captured_at (ISO)
     * @return array{valid: bool, reason: string|null}
     */
    public function verify(?string $token, ?string $capturedAt): array
    {
        if (! $token || ! $capturedAt) {
            return $this->failure('attestation_missing', 'Token atau captured_at tidak ada', 'retake_photo');
        }

        try {
            $decrypted = Crypt::decryptString($token);
            $payload = json_decode($decrypted, true);
        } catch (\Throwable $e) {
            return $this->failure('attestation_invalid', 'Token tidak valid (rusak/di-tamper)', 'retake_photo');
        }

        if (! $payload || ! isset($payload['nonce'], $payload['issued_at'])) {
            return $this->failure('attestation_invalid', 'Payload token tidak lengkap', 'retake_photo');
        }

        $nonce = $payload['nonce'];
        $issuedAt = Carbon::createFromTimestamp($payload['issued_at']);
        $cacheKey = self::CACHE_PREFIX.$nonce;
        $usedKey = self::USED_PREFIX.$nonce;

        if (Cache::has($usedKey)) {
            return $this->failure('attestation_used', 'Token sudah dipakai', 'go_to_history');
        }

        if (! Cache::has($cacheKey)) {
            return $this->failure('attestation_expired', 'Nonce sudah kedaluwarsa', 'retake_photo');
        }

        if ($issuedAt->isFuture()
            || Carbon::now()->timestamp - $issuedAt->timestamp > self::TOKEN_TTL) {
            return $this->failure('attestation_expired', 'Token kedaluwarsa', 'retake_photo');
        }

        try {
            $clientTime = Carbon::parse($capturedAt);
        } catch (\Throwable $e) {
            return $this->failure('attestation_invalid', 'Format captured_at tidak valid', 'retake_photo');
        }

        $skew = $clientTime->timestamp - Carbon::now()->timestamp;

        if ($skew > self::MAX_CLOCK_SKEW) {
            return $this->failure('device_clock_ahead', 'Waktu perangkat lebih cepat dari server', 'set_clock', $skew);
        }

        if ($skew < -self::MAX_CLOCK_SKEW) {
            return $this->failure('device_clock_behind', 'Waktu perangkat lebih lambat dari server', 'set_clock', abs($skew));
        }

        if ($clientTime->lt($issuedAt)) {
            return $this->failure('captured_before_session', 'Waktu foto sebelum sesi diterbitkan', 'retake_photo');
        }

        Cache::put($usedKey, true, self::TOKEN_TTL);
        Cache::forget($cacheKey);

        return ['valid' => true, 'reason' => null];
    }

    private function failure(string $code, string $reason, string $action, ?int $difference = null): array
    {
        return [
            'valid' => false,
            'code' => $code,
            'reason' => $reason,
            'action' => $action,
            'difference_seconds' => $difference,
        ];
    }
}
