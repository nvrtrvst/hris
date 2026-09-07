<?php

namespace App\Traits;

use App\Helpers\DistanceHelper;

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
        return (int) round(DistanceHelper::haversine($lat1, $lon1, $lat2, $lon2));
    }
}
