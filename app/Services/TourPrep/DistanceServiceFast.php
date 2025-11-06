<?php
// app/Services/TourPrep/DistanceServiceFast.php
namespace App\Services\TourPrep;

class DistanceServiceFast
{
    /**
     * Distance (m) + durée (s) à ~km/h entre deux points {lat,lon}
     * Retourne [dist_m, duree_s] (compatible avec l'existant).
     */
    public function segment(array $a, array $b, $kmh = 45): array
    {
        $dKm    = $this->haversine($a['lat'], $a['lon'], $b['lat'], $b['lon']);
        $dist_m = (int) round($dKm * 1000);
        $kmh    = max((int) $kmh, 1);
        $duree_s= (int) round(($dKm / $kmh) * 3600);
        return [$dist_m, $duree_s];
    }

    /**
     * ✅ Helper demandé par AutoplanService : distance en mètres
     */
    public function distanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): int
    {
        $dKm = $this->haversine($lat1, $lon1, $lat2, $lon2);
        return (int) round($dKm * 1000);
    }

    /**
     * Optionnel mais pratique : durée (s) à ~km/h entre 2 points.
     */
    public function durationSeconds(float $lat1, float $lon1, float $lat2, float $lon2, int $kmh = 45): int
    {
        $kmh = max($kmh, 1);
        $dKm = $this->haversine($lat1, $lon1, $lat2, $lon2);
        return (int) round(($dKm / $kmh) * 3600);
    }

    /**
     * Haversine (distance en km).
     */
    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $R   = 6371.0; // km
        $dLat= deg2rad($lat2 - $lat1);
        $dLon= deg2rad($lon2 - $lon1);
        $a   = sin($dLat/2) * sin($dLat/2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
        $c   = 2 * atan2(sqrt($a), sqrt(1-$a));
        return $R * $c;
    }
}
