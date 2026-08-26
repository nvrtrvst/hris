<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Reverse-geocode koordinat GPS -> kecamatan/kelurahan (Indonesia).
 *
 * Pakai Nominatim (OpenStreetMap) — data kelurahan/desa lengkap untuk
 * Indonesia. Gratis, tanpa API key, rate limit 1 req/detik.
 *
 * Fail-open: timeout/error -> null (overlay tetap jalan tanpa alamat).
 * Hasil di-cache per koordinat (pembulatan 4 desimal ≈ 11 m) 30 hari agar
 * absen berulang di lokasi sama tidak menembak API berulang.
 */
class GeocodingService
{
    private const TTL = 86400 * 30;

    private const BASE = 'https://nominatim.openstreetmap.org/reverse';

    private const TIMEOUT = 5;

    /**
     * @return array{kecamatan:?string, kelurahan:?string}
     */
    public function reverse($lat, $lng): array
    {
        if ($lat === null || $lng === null) {
            return $this->empty();
        }

        $latitude = (float) $lat;
        $longitude = (float) $lng;
        if ($latitude === 0.0 && $longitude === 0.0) {
            return $this->empty();
        }

        $cacheKey = 'geo_rev_'.round($latitude, 4).'_'.round($longitude, 4);

        return Cache::remember($cacheKey, self::TTL, function () use ($latitude, $longitude) {
            try {
                $response = Http::withHeaders([
                    'User-Agent' => 'HRIS-Yayasan/1.0 (presensi photo overlay)',
                ])->timeout(self::TIMEOUT)->get(self::BASE, [
                    'lat' => $latitude,
                    'lon' => $longitude,
                    'format' => 'json',
                    'addressdetails' => 1,
                    'accept-language' => 'id',
                ]);

                if (! $response->successful()) {
                    return $this->empty();
                }

                $address = $response->json('address', []);

                // Prioritas: subdistrict (kecamatan) → village (kelurahan/desa) → city (kabupaten)
                $kecamatan = $address['subdistrict'] ?? $address['city_district'] ?? null;
                $kelurahan = $address['village'] ?? $address['hamlet'] ?? null;
                $kabupaten = $address['city'] ?? $address['county'] ?? null;

                return ['kecamatan' => $kecamatan, 'kelurahan' => $kelurahan, 'kabupaten' => $kabupaten];
            } catch (\Throwable $e) {
                return $this->empty();
            }
        });
    }

    /**
     * @return array{kecamatan:?string, kelurahan:?string}
     */
    private function empty(): array
    {
        return ['kecamatan' => null, 'kelurahan' => null, 'kabupaten' => null];
    }
}
