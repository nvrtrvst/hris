<?php

namespace App\Helpers;

/**
 * Haversine distance (meters) between two GPS coordinates.
 * Single source of truth — replaces CalculatesDistance trait, SpoofDetector, ProcessPresensiFoto copies.
 */
class DistanceHelper
{
    private const EARTH_RADIUS = 6371000; // meters

    /**
     * Haversine formula: returns distance in meters (float).
     */
    public static function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
           * sin($dLon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS * $c;
    }
}
