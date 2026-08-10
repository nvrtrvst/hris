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

    private const TOKEN_TTL = 600; // 10 menit

    private const MAX_CLOCK_SKEW = 60; // detik ke depan

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
            return ['valid' => false, 'reason' => 'Token atau captured_at tidak ada'];
        }

        try {
            $decrypted = Crypt::decryptString($token);
            $payload = json_decode($decrypted, true);
        } catch (\Throwable $e) {
            return ['valid' => false, 'reason' => 'Token tidak valid (rusak/di-tamper)'];
        }

        if (! $payload || ! isset($payload['nonce'], $payload['issued_at'])) {
            return ['valid' => false, 'reason' => 'Payload token tidak lengkap'];
        }

        $nonce = $payload['nonce'];
        $issuedAt = Carbon::createFromTimestamp($payload['issued_at']);

        // Cek nonce replay (sudah dipakai?)
        $cacheKey = self::CACHE_PREFIX.$nonce;
        if (! Cache::has($cacheKey)) {
            return ['valid' => false, 'reason' => 'Token sudah kedaluwarsa atau sudah dipakai'];
        }

        // Hapus nonce setelah diverifikasi (sekali pakai)
        Cache::forget($cacheKey);

        // Cek expired (absolute diff — handle jika issuedAt di masa depan)
        if ($issuedAt->isFuture() || Carbon::now()->diffInSeconds($issuedAt, true) > self::TOKEN_TTL) {
            return ['valid' => false, 'reason' => 'Token kedaluwarsa atau tidak valid'];
        }

        // Cek captured_at tidak sebelum token diterbitkan
        try {
            $clientTime = Carbon::parse($capturedAt);
        } catch (\Throwable $e) {
            return ['valid' => false, 'reason' => 'Format captured_at tidak valid'];
        }

        if ($clientTime->lt($issuedAt)) {
            return ['valid' => false, 'reason' => 'captured_at sebelum token diterbitkan (waktu HP mundur atau replay)'];
        }

        // Cek captured_at tidak terlalu ke depan (max 60 detik ke depan dari server)
        if ($clientTime->diffInSeconds(Carbon::now(), false) > self::MAX_CLOCK_SKEW) {
            return ['valid' => false, 'reason' => 'captured_at terlalu maju (>60 detik dari server)'];
        }

        return ['valid' => true, 'reason' => null];
    }
}
