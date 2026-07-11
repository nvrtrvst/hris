<?php

namespace App\Traits;

/**
 * Trait untuk menghitung jarak (Haversine formula) antara dua koordinat GPS.
 * Digunakan di PresensiController dan MobileController.
 */
trait CalculatesDistance
{
    /**
     * Menghitung jarak antara dua titik koordinat menggunakan Haversine formula.
     *
     * @param  float  $lat1  Latitude titik 1
     * @param  float  $lon1  Longitude titik 1
     * @param  float  $lat2  Latitude titik 2
     * @param  float  $lon2  Longitude titik 2
     * @return int Jarak dalam meter (dibulatkan)
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2): int
    {
        $earthRadius = 6371000; // Radius bumi dalam meter
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2)
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
           * sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * asin(sqrt($a));

        return (int) round($earthRadius * $c);
    }
}
