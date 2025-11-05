<?php
// app/Services/TourPrep/DistanceServiceOsrm.php
namespace App\Services\TourPrep;

class DistanceServiceOsrm
{
    public function route(array $points): ?array
    {
        if (count($points) < 2) return null;

        $coords = [];
        foreach ($points as $p) {
            $coords[] = $p[1] . ',' . $p[0]; // lon,lat pour OSRM
        }
        $url = 'https://router.project-osrm.org/route/v1/driving/' . implode(';', $coords)
            . '?overview=full&annotations=distance,duration&geometries=geojson';

        $ctx = stream_context_create(['http'=>[
            'method'=>'GET',
            'header'=>"User-Agent: Maintronic-TourPrep/1.0\r\n",
            'timeout'=>8
        ]]);
        $json = @file_get_contents($url, false, $ctx);
        if (!$json) return null;

        $res = json_decode($json, true);
        if (!is_array($res) || empty($res['routes'][0])) return null;

        $r = $res['routes'][0];
        return [
            'distance_m' => (int) round($r['distance']),
            'duration_s' => (int) round($r['duration']),
            'geometry'   => $r['geometry'], // GeoJSON LineString
        ];
    }
}
