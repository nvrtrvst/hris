<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Detektor sinyal spoofing (fake GPS / emulator) untuk presensi.
 *
 * Analisis multi-faktor:
 * 1. Trajectory 3-titik (awal → A → B): pola pergerakan tidak realistis
 * 2. Accelerometer samples: device virtual tanpa sensor fisik
 * 3. Kecepatan: speed GPS tidak masuk akal untuk lokasi indoor
 * 4. IP Geolocation cross-reference: IP tidak sesuai dengan koordinat GPS
 *
 * Semua metode fail-open: jika data tidak tersedia, return not-suspect.
 */
class SpoofDetector
{
    private const MAX_INDOOR_SPEED = 5; // m/s (18 km/jam — lari kencang)

    private const MAX_INDOOR_SPEED_STRICT = 10; // m/s (36 km/jam — mustahil di dalam gedung)

    private const MOTION_ZERO_THRESHOLD = 1e-6; // varians total akselerasi; cuma sinyal konstan persis (emulator tanpa sensor) yang ke-flag, bukan hp diam biasa

    private const IP_GEO_CACHE_TTL = 86400; // 24 jam per IP

    private const IP_GEO_DISTANCE_THRESHOLD = 500; // km

    /**
     * Analisis trajectory 3 titik (posAwal, posA, posB).
     *
     * @param  array|null  $points  [['label'=>'awal','lat'=>-6.2,'lng'=>106.8,'accuracy'=>15,'captured_at'=>'...'], ...]
     * @return array{suspect: bool, reasons: string[]}
     */
    public function analyzeTrajectory(?array $points): array
    {
        $reasons = [];

        if (! $points || count($points) < 2) {
            return ['suspect' => false, 'reasons' => []];
        }

        // Urutkan berdasarkan captured_at (label: awal, a, b)
        $sorted = collect($points)->filter(fn ($p) => isset($p['lat'], $p['lng']))->values();

        if ($sorted->count() < 2) {
            return ['suspect' => false, 'reasons' => []];
        }

        // Cek 1: Semua titik koordinat IDENTIK (dalam 0.00001 derajat ≈ 1m)
        $allIdentical = true;
        $firstLat = (float) $sorted[0]['lat'];
        $firstLng = (float) $sorted[0]['lng'];
        foreach ($sorted as $p) {
            if (abs((float) $p['lat'] - $firstLat) > 0.00001 || abs((float) $p['lng'] - $firstLng) > 0.00001) {
                $allIdentical = false;
                break;
            }
        }
        // Hanya curigai jika ada selisih waktu > 5 dtk (selain itu wajar kalau standing still)
        if ($allIdentical && $sorted->count() >= 2) {
            $t0 = isset($sorted[0]['captured_at']) ? Carbon::parse($sorted[0]['captured_at']) : null;
            $t1 = isset($sorted[$sorted->count() - 1]['captured_at']) ? Carbon::parse($sorted[$sorted->count() - 1]['captured_at']) : null;
            if ($t0 && $t1 && $t0->diffInSeconds($t1) > 5) {
                $reasons[] = 'trajectory: titik identik >5 detik (tidak ada jitter GPS)';
            }
        }

        // Cek 2: Kecepatan antar-titik tidak realistis
        for ($i = 0; $i < $sorted->count() - 1; $i++) {
            $p1 = $sorted[$i];
            $p2 = $sorted[$i + 1];
            if (! isset($p1['captured_at'], $p2['captured_at'])) {
                continue;
            }
            $t1 = Carbon::parse($p1['captured_at']);
            $t2 = Carbon::parse($p2['captured_at']);
            $dt = $t1->diffInSeconds($t2);
            if ($dt <= 0) {
                continue;
            }
            $distance = $this->calculateDistance(
                (float) $p1['lat'], (float) $p1['lng'],
                (float) $p2['lat'], (float) $p2['lng']
            );
            $speed = $distance / $dt; // m/s
            if ($speed > self::MAX_INDOOR_SPEED_STRICT) {
                $reasons[] = 'trajectory: kecepatan '.round($speed, 1).' m/s antar '.$p1['label'].'→'.$p2['label'].' (tidak realistis)';
            } elseif ($speed > self::MAX_INDOOR_SPEED) {
                $reasons[] = 'trajectory: kecepatan '.round($speed, 1).' m/s antar '.$p1['label'].'→'.$p2['label'].' (lari kencang)';
            }
        }

        // Cek 3: Timestamp tidak monolitik (waktu sama untuk titik berbeda)
        if ($sorted->count() >= 2) {
            $timestamps = $sorted->pluck('captured_at')->filter()->unique()->values();
            if ($timestamps->count() === 1 && $sorted->count() > 1) {
                $reasons[] = 'trajectory: semua titik punya timestamp sama (tidak mungkin GPS real)';
            }
        }

        return ['suspect' => count($reasons) > 0, 'reasons' => $reasons];
    }

    /**
     * Analisis sampel accelerometer.
     *
     * @param  array|null  $samples  [{x:float, y:float, z:float, timestamp:int}, ...]
     * @return array{suspect: bool, reasons: string[], variance: ?float}
     */
    public function analyzeMotion(?array $samples): array
    {
        $reasons = [];

        if (! $samples || count($samples) < 2) {
            return ['suspect' => false, 'reasons' => [], 'variance' => null];
        }

        // Hitung total akselerasi per sample (termasuk gravitasi ≈ 9.8)
        $totals = [];
        foreach ($samples as $s) {
            if (! isset($s['x'], $s['y'], $s['z'])) {
                continue;
            }
            $totals[] = sqrt((float) $s['x'] ** 2 + (float) $s['y'] ** 2 + (float) $s['z'] ** 2);
        }

        if (count($totals) < 2) {
            return ['suspect' => false, 'reasons' => [], 'variance' => null];
        }

        // Varians
        $mean = array_sum($totals) / count($totals);
        $variance = array_sum(array_map(fn ($v) => ($v - $mean) ** 2, $totals)) / count($totals);

        // Semua nol? → emulator
        if (max($totals) < 0.01) {
            $reasons[] = 'motion: semua sample akselerasi ~0 (device virtual / emulator)';
        } elseif ($variance < self::MOTION_ZERO_THRESHOLD) {
            // Varians terlalu rendah → device terpasang statis (bukan digenggam)
            $reasons[] = 'motion: varians akselerasi '.sprintf('%.6f', $variance).' (terlalu stabil)';
        }

        return ['suspect' => count($reasons) > 0, 'reasons' => $reasons, 'variance' => $variance];
    }

    /**
     * Analisis kecepatan GPS.
     *
     * @param  float|null  $speed  m/s dari GPS
     * @return array{suspect: bool, reasons: string[]}
     */
    public function analyzeSpeed(?float $speed): array
    {
        if ($speed === null) {
            return ['suspect' => false, 'reasons' => []];
        }

        if ($speed > self::MAX_INDOOR_SPEED_STRICT) {
            return ['suspect' => true, 'reasons' => ['speed: kecepatan '.round($speed, 1).' m/s tidak realistis untuk presensi']];
        }

        if ($speed > self::MAX_INDOOR_SPEED) {
            return ['suspect' => false, 'reasons' => ['speed: kecepatan '.round($speed, 1).' m/s (perhatian)']];
        }

        return ['suspect' => false, 'reasons' => []];
    }

    /**
     * Cross-reference IP geolocation dengan koordinat GPS.
     *
     * @param  string|null  $ip  Client IP
     * @param  float  $gpsLat  GPS latitude
     * @param  float  $gpsLng  GPS longitude
     * @return array{suspect: bool, reasons: string[], geo: ?array}
     */
    public function analyzeGeoIp(?string $ip, float $gpsLat, float $gpsLng): array
    {
        if (! $ip || $ip === '127.0.0.1' || $ip === '::1') {
            return ['suspect' => false, 'reasons' => [], 'geo' => ['ip' => $ip]];
        }

        // Cache per IP (24 jam)
        $cacheKey = 'ip_geo_'.md5($ip);
        $geo = Cache::remember($cacheKey, self::IP_GEO_CACHE_TTL, function () use ($ip) {
            return $this->fetchIpGeo($ip);
        });

        if (! $geo || ! isset($geo['latitude'], $geo['longitude'])) {
            return ['suspect' => false, 'reasons' => [], 'geo' => ['ip' => $ip]];
        }

        $distanceKm = $this->calculateDistance($gpsLat, $gpsLng, (float) $geo['latitude'], (float) $geo['longitude']) / 1000;

        $reasons = [];
        if ($distanceKm > self::IP_GEO_DISTANCE_THRESHOLD) {
            $reasons[] = 'ip-geo: jarak GPS vs IP '.round($distanceKm).' km (threshold '.self::IP_GEO_DISTANCE_THRESHOLD.'km)';
        }

        return [
            'suspect' => count($reasons) > 0,
            'reasons' => $reasons,
            'geo' => [
                'ip' => $ip,
                'city' => $geo['city'] ?? null,
                'country' => $geo['country'] ?? null,
                'latitude' => $geo['latitude'],
                'longitude' => $geo['longitude'],
                'distance_km' => round($distanceKm, 1),
            ],
        ];
    }

    /**
     * Fetch IP geolocation dari ip-api.com (free, 45 req/min, unlimited for non-commercial).
     * Fail-open: return null jika error.
     */
    private function fetchIpGeo(string $ip): ?array
    {
        try {
            $response = Http::timeout(3)->get('http://ip-api.com/json/'.$ip, [
                'fields' => 'city,country,countryCode,lat,lon',
            ]);

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json();

            return [
                'city' => $data['city'] ?? null,
                'country' => $data['country'] ?? null,
                'country_code' => $data['countryCode'] ?? null,
                'latitude' => $data['lat'] ?? null,
                'longitude' => $data['lon'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::warning('IP geolocation gagal', ['ip' => $ip, 'error' => $e->getMessage()]);

            return null;
        }
    }

    private function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
