<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Reverse-geocode koordinat GPS -> kecamatan/kelurahan (Indonesia).
 *
 * Pakai BigDataCloud (gratis, tanpa API key — sama seperti geocode di
 * useGeolocation.js frontend). Admin-level di respons tidak seragam untuk
 * Indonesia (principalSubdivision malah berisi nama pulau), jadi kita parse
 * dari field `description` yang berbahasa Indonesia ("kecamatan di …",
 * "kelurahan di …", "desa di …").
 *
 * Fail-open: timeout/error -> null (overlay tetap jalan tanpa alamat).
 * Hasil di-cache per koordinat (pembulatan 4 desimal ≈ 11 m) 30 hari agar
 * absen berulang di lokasi sama tidak menembak API berulang.
 */
class GeocodingService
{
    private const TTL = 86400 * 30;

    private const BASE = 'https://api.bigdatacloud.net/data/reverse-geocode-client';

    private const TIMEOUT = 3;

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
                $response = Http::timeout(self::TIMEOUT)->get(self::BASE, [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'localityLanguage' => 'id',
                ]);

                if (! $response->successful()) {
                    return $this->empty();
                }

                $data = $response->json();
                $kecamatan = $this->findByDescription($data, 'kecamatan');
                $kelurahan = $this->findByDescription($data, 'kelurahan') ?? $this->findByDescription($data, 'desa');

                return ['kecamatan' => $kecamatan, 'kelurahan' => $kelurahan];
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
        return ['kecamatan' => null, 'kelurahan' => null];
    }

    private function findByDescription(array $data, string $keyword): ?string
    {
        $items = array_merge(
            $data['localityInfo']['administrative'] ?? [],
            $data['localityInfo']['informative'] ?? [],
        );

        foreach ($items as $item) {
            $description = (string) ($item['description'] ?? '');
            if ($description !== '' && stripos($description, $keyword) !== false && ! empty($item['name'])) {
                return $item['name'];
            }
        }

        return null;
    }
}
