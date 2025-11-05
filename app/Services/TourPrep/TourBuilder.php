<?php
namespace App\Services\TourPrep;

use Illuminate\Support\Collection;

class TourBuilder
{
    /** @var GeocodeService */
    protected $geo;
    /** @var DistanceServiceFast */
    protected $fast;
    /** @var DistanceServiceOsrm */
    protected $osrm;

    public function __construct(GeocodeService $geo, DistanceServiceFast $fast, DistanceServiceOsrm $osrm)
    {
        $this->geo  = $geo;
        $this->fast = $fast;
        $this->osrm = $osrm;
    }

    /**
     * Construit la tournée en incluant le RETOUR à l'agence.
     * $mode: 'fast' | 'precise' (OSRM). $optimize: heuristique NN.
     */
    public function build(array $start, Collection $rdvs, $mode = 'fast', $optimize = false)
    {
        $stops = [];
        foreach ($rdvs as $r) {
            $street = trim(preg_replace('/\s+/', ' ', (string)($r->AdLivCli ?? '')));
            $cp     = trim((string)($r->CPLivCli ?? ''));
            $city   = trim((string)($r->VilleLivCli ?? ''));

            $g = $this->geo->geocodeParts($street, $cp, $city);
            if (!$g) continue;

            $stops[] = [
                'numint' => $r->NumInt,
                'heure'  => $r->HeureIntPrevu,
                'label'  => $g['label'],
                'lat'    => $g['lat'],
                'lon'    => $g['lon'],

                // Pour l’UI
                'city'   => $city,
                'cp'     => $cp,
                'street' => $street,
                'brand'  => $r->Marque ?? null,
                'type'   => $r->TypeApp ?? null,
                'urgent' => (bool)($r->urgent ?? 0),
                'comment'=> $r->commentaire ?? null,
            ];
        }

        if (empty($stops)) {
            return ['stops'=>[], 'segments'=>[], 'total'=>['dist_m'=>0,'dur_s'=>0,'over6h'=>false], 'geometry'=>null];
        }

        // Ordonnancement
        if ($optimize) {
            $order = $this->nearestNeighborOrder($start, $stops);
        } else {
            usort($stops, fn($a,$b) => strcmp($a['heure'] ?? '00:00:00', $b['heure'] ?? '00:00:00'));
            $order = array_keys($stops);
        }

        // Séquence ALLER + RETOUR agence
        $seq = [ [ $start['lat'], $start['lon'] ] ];
        foreach ($order as $idx) {
            $s = $stops[$idx];
            $seq[] = [ $s['lat'], $s['lon'] ];
        }
        $seq[] = [ $start['lat'], $start['lon'] ];

        $segments = [];
        $totalDist = 0; $totalDur = 0; $geometry = null;

        if ($mode === 'precise') {
            $osrm = $this->osrm->route($seq);
            if ($osrm) {
                $totalDist = $osrm['distance_m'];
                $totalDur  = $osrm['duration_s'];
                $geometry  = $osrm['geometry'];
            } else {
                $mode = 'fast';
            }
        }

        if ($mode === 'fast') {
            $prev = $seq[0];
            for ($i = 1; $i < count($seq); $i++) {
                $pt = $seq[$i];
                [$dist, $dur] = $this->fast->segment(['lat'=>$prev[0],'lon'=>$prev[1]], ['lat'=>$pt[0],'lon'=>$pt[1]]);
                $segments[] = ['to_index'=>$i, 'dist_m'=>$dist, 'dur_s'=>$dur];
                $totalDist += $dist; $totalDur += $dur;
                $prev = $pt;
            }
        }

        $orderedStops = [];
        foreach ($order as $i => $idx) $orderedStops[] = $stops[$idx];

        return [
            'stops'    => $orderedStops,
            'segments' => $segments,
            'total'    => ['dist_m'=>$totalDist,'dur_s'=>$totalDur,'over6h'=>($totalDur > 6*3600)],
            'geometry' => $geometry
        ];
    }

    private function nearestNeighborOrder(array $start, array $stops)
    {
        $unused = array_keys($stops);
        $order  = [];
        $cur = ['lat' => $start['lat'], 'lon' => $start['lon']];

        while (!empty($unused)) {
            $bestK = null; $bestD = PHP_INT_MAX;
            foreach ($unused as $k) {
                $s = $stops[$k];
                $seg = $this->fast->segment($cur, ['lat'=>$s['lat'],'lon'=>$s['lon']]);
                $d = $seg[0];
                if ($d < $bestD) { $bestD = $d; $bestK = $k; }
            }
            $order[] = $bestK;
            $cur = ['lat' => $stops[$bestK]['lat'], 'lon' => $stops[$bestK]['lon']];
            $unused = array_values(array_diff($unused, [$bestK]));
        }
        return $order;
    }
}
