<?php
// app/Services/TourPrep/DistanceServiceFast.php
namespace App\Services\TourPrep;

class DistanceServiceFast
{
    public function segment(array $a, array $b, $kmh = 45): array
    {
        $dKm = $this->haversine($a['lat'], $a['lon'], $b['lat'], $b['lon']);
        $dist_m = (int) round($dKm * 1000);
        $kmh = max((int)$kmh, 1);
        $duree_s = (int) round(($dKm / $kmh) * 3600);
        return [$dist_m, $duree_s];
    }

    private function haversine($lat1, $lon1, $lat2, $lon2)
    {
        $R = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat/2) * sin($dLat/2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return $R * $c;
    }
}
