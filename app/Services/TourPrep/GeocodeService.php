<?php
namespace App\Services\TourPrep;

use Illuminate\Support\Facades\DB;

class GeocodeService
{
    /* ======================= API PUBLIQUE ======================= */

    public function geocodeTry(array $candidates): ?array
    {
        foreach ($candidates as $label) {
            $label = trim((string)$label);
            if ($label === '') continue;
            $hit = $this->geocode($label);
            if ($hit) return $hit;
        }
        return null;
    }

    public function geocode($label): ?array
    {
        $label = trim(preg_replace('/\s+/', ' ', (string)$label));
        if ($label === '') return null;

        $hash = hash('sha256', mb_strtoupper($label));

        // Cache hit ?
        $row = DB::table('geo_cache')->where('addr_hash', $hash)->first();
        if ($row && $row->lat !== null && $row->lon !== null) {
            DB::table('geo_cache')->where('addr_hash', $hash)->update([
                'hits'       => DB::raw('hits + 1'),
                'last_hit_at'=> now(),
            ]);
            return ['lat' => (float)$row->lat, 'lon' => (float)$row->lon, 'label' => $row->label];
        }

        // BAN d'abord
        [$lat, $lon] = $this->fetchBAN($label, null, null, false);
        if ($lat !== null && $lon !== null) {
            DB::table('geo_cache')->updateOrInsert(
                ['addr_hash' => $hash],
                ['label'=>$label,'lat'=>$lat,'lon'=>$lon,'source'=>'ban','hits'=>DB::raw('COALESCE(hits,0)+1'),'last_hit_at'=>now(),'updated_at'=>now(),'created_at'=>now()]
            );
            return ['lat'=>$lat,'lon'=>$lon,'label'=>$label];
        }

        // Nominatim libre
        [$lat, $lon] = $this->fetchFreeText($label);
        $src = ($lat !== null) ? 'nominatim' : 'nf';
        DB::table('geo_cache')->updateOrInsert(
            ['addr_hash' => $hash],
            ['label'=>$label,'lat'=>$lat,'lon'=>$lon,'source'=>$src,'hits'=>DB::raw('COALESCE(hits,0)+1'),'last_hit_at'=>now(),'updated_at'=>now(),'created_at'=>now()]
        );

        return ($lat !== null && $lon !== null) ? ['lat'=>$lat,'lon'=>$lon,'label'=>$label] : null;
    }

    /**
     * Géocode Ad/CP/Ville en minimisant les appels et en respectant le CP si présent.
     */
    public function geocodeParts(?string $ad, ?string $cp, ?string $ville): ?array
    {
        $ad    = $this->cleanStreet($ad ?? '');
        $cp    = $this->cleanCp($cp ?? '');
        $ville = $this->cleanCity($ville ?? '');

        // Cas avec CP (priorité stricte au CP)
        if ($cp !== '') {
            // 1) Rue+Ville+CP — BAN (enforce CP), sinon Nominatim structuré (enforce CP)
            if ($ad !== '' && ($ville !== '' || $cp !== '')) {
                $label = $this->label($ad,$cp,$ville);
                $hit = $this->geocodeWithCacheKey($label, function () use ($ad,$ville,$cp) {
                    [$lat, $lon] = $this->fetchBAN("$ad $cp $ville", $cp, $ville ?: null, true);
                    if ($lat !== null) return [$lat,$lon,'ban'];
                    [$lat, $lon] = $this->fetchStructured($ad, $ville, $cp, true); // enforce CP
                    return [$lat, $lon, ($lat!==null?'nominatim':null)];
                });
                if ($hit) return $hit;
            }

            // 2) Ville+CP (centroïde), CP respecté
            if ($ville !== '') {
                $label = trim($cp.' '.$ville);
                $hit = $this->geocodeWithCacheKey($label, function () use ($ville,$cp) {
                    [$lat, $lon] = $this->fetchBAN("$cp $ville", $cp, $ville, true);
                    if ($lat !== null) return [$lat,$lon,'ban'];
                    [$lat, $lon] = $this->fetchStructured('', $ville, $cp, true);
                    return [$lat, $lon, ($lat!==null?'nominatim':null)];
                });
                if ($hit) return $hit;
            } else {
                // 2bis) CP seul (centroïde)
                $label = $cp;
                $hit = $this->geocodeWithCacheKey($label, function () use ($cp) {
                    [$lat, $lon] = $this->fetchBAN($cp, $cp, null, true);
                    if ($lat !== null) return [$lat,$lon,'ban'];
                    [$lat, $lon] = $this->fetchStructured('', '', $cp, true);
                    return [$lat, $lon, ($lat!==null?'nominatim':null)];
                });
                if ($hit) return $hit;
            }

            // 3) Fallback “ville seule” (sans forcer CP), peu coûteux
            if ($ville !== '') {
                $label = $ville;
                $hit = $this->geocodeWithCacheKey($label, function () use ($ville) {
                    [$lat, $lon] = $this->fetchBAN($ville, null, $ville, false);
                    if ($lat !== null) return [$lat,$lon,'ban'];
                    [$lat, $lon] = $this->fetchStructured('', $ville, '', false);
                    return [$lat, $lon, ($lat!==null?'nominatim':null)];
                });
                if ($hit) return $hit;
            }

            return null;
        }

        // Cas sans CP : séquence standard
        $tries = [
            // Rue+Ville
            function () use ($ad,$ville) {
                if ($ad !== '' && $ville !== '') {
                    $label = $this->label($ad,'',$ville);
                    return $this->geocodeWithCacheKey($label, function () use ($ad,$ville) {
                        [$lat, $lon] = $this->fetchBAN("$ad $ville", null, $ville, false);
                        if ($lat !== null) return [$lat,$lon,'ban'];
                        [$lat, $lon] = $this->fetchStructured($ad,$ville,'', false);
                        return [$lat, $lon, ($lat!==null?'nominatim':null)];
                    });
                }
                return null;
            },
            // Rue seule
            function () use ($ad) {
                if ($ad !== '') {
                    $label = $ad;
                    return $this->geocodeWithCacheKey($label, function () use ($ad) {
                        [$lat, $lon] = $this->fetchBAN($ad, null, null, false);
                        if ($lat !== null) return [$lat,$lon,'ban'];
                        [$lat, $lon] = $this->fetchFreeText($ad);
                        return [$lat, $lon, ($lat!==null?'nominatim':null)];
                    });
                }
                return null;
            },
            // Ville seule
            function () use ($ville) {
                if ($ville !== '') {
                    $label = $ville;
                    return $this->geocodeWithCacheKey($label, function () use ($ville) {
                        [$lat, $lon] = $this->fetchBAN($ville, null, $ville, false);
                        if ($lat !== null) return [$lat,$lon,'ban'];
                        [$lat, $lon] = $this->fetchStructured('', $ville, '', false);
                        return [$lat, $lon, ($lat!==null?'nominatim':null)];
                    });
                }
                return null;
            },
        ];

        foreach ($tries as $fn) {
            $g = $fn();
            if ($g) return $g;
        }
        return null;
    }

    /* ======================= IMPLÉMENTATION ======================= */

    /**
     * Enveloppe cache: si hit → ++hits/last_hit_at ; sinon exécute $fetcher et insère.
     * $fetcher doit retourner: [lat|null, lon|null, source|null]
     */
    private function geocodeWithCacheKey(string $label, callable $fetcher): ?array
    {
        $norm = trim(preg_replace('/\s+/', ' ', $label));
        if ($norm === '') return null;

        $hash = hash('sha256', mb_strtoupper($norm));

        $row = DB::table('geo_cache')->where('addr_hash', $hash)->first();
        if ($row && $row->lat !== null && $row->lon !== null) {
            DB::table('geo_cache')->where('addr_hash', $hash)->update([
                'hits'        => DB::raw('hits + 1'),
                'last_hit_at' => now(),
            ]);
            return ['lat'=>(float)$row->lat, 'lon'=>(float)$row->lon, 'label'=>$row->label];
        }

        $triple = $fetcher();
        $lat = $triple[0] ?? null;
        $lon = $triple[1] ?? null;
        $src = $triple[2] ?? (($lat!==null && $lon!==null) ? 'nominatim' : 'nf');

        DB::table('geo_cache')->updateOrInsert(
            ['addr_hash' => $hash],
            [
                'label'       => $norm,
                'lat'         => $lat,
                'lon'         => $lon,
                'source'      => $src,
                'hits'        => DB::raw('COALESCE(hits,0)+1'),
                'last_hit_at' => now(),
                'updated_at'  => now(),
                'created_at'  => now(),
            ]
        );

        return ($lat !== null && $lon !== null) ? ['lat'=>$lat,'lon'=>$lon,'label'=>$norm] : null;
    }

    /** BAN — peut forcer un match CP via comparaison de properties.postcode */
    private function fetchBAN(string $q, ?string $cp, ?string $ville, bool $enforceCp): array
    {
        $params = ['q' => $q, 'limit' => 1, 'autocomplete' => 0];
        if ($cp)    $params['postcode'] = $cp;
        if ($ville) $params['city']     = $ville;

        $url  = 'https://api-adresse.data.gouv.fr/search/?' . http_build_query($params);
        $json = $this->httpGet($url, 5);
        if (!$json) return [null, null];

        $o = json_decode($json, true);
        if (empty($o['features'][0]['geometry']['coordinates'])) return [null, null];

        $feat = $o['features'][0];
        $retCp = $feat['properties']['postcode'] ?? null;

        if ($enforceCp && $cp && $retCp && trim($retCp) !== trim($cp)) {
            return [null, null]; // CP incohérent => rejet
        }

        $lon = (float)$feat['geometry']['coordinates'][0];
        $lat = (float)$feat['geometry']['coordinates'][1];
        return [$lat, $lon];
    }

    /** Nominatim structuré — avec addressdetails=1 pour vérifier le CP si fourni */
    private function fetchStructured(string $street, string $city, string $postal, bool $enforceCp): array
    {
        $q = array_filter([
            'format'         => 'json',
            'limit'          => 1,
            'addressdetails' => 1,          // <- pour lire address.postcode
            'countrycodes'   => 'fr',
            'street'         => $street ?: null,
            'city'           => $city   ?: null,
            'postalcode'     => $postal ?: null,
        ]);

        $url  = 'https://nominatim.openstreetmap.org/search?'.http_build_query($q);
        $json = $this->httpGet($url, 6);
        if (!$json) return [null, null];

        $arr = json_decode($json, true);
        if (empty($arr[0])) return [null, null];

        $addrPost = $arr[0]['address']['postcode'] ?? null;
        if ($enforceCp && $postal !== '' && $addrPost && trim($addrPost) !== trim($postal)) {
            return [null, null]; // CP incohérent => rejet
        }

        $lat = isset($arr[0]['lat']) ? (float)$arr[0]['lat'] : null;
        $lon = isset($arr[0]['lon']) ? (float)$arr[0]['lon'] : null;
        return [$lat, $lon];
    }

    /** Nominatim libre (fallback) */
    private function fetchFreeText(string $text): array
    {
        $q = [
            'format'         => 'json',
            'limit'          => 1,
            'addressdetails' => 0,
            'countrycodes'   => 'fr',
            'q'              => trim($text.' France'),
        ];
        $url = 'https://nominatim.openstreetmap.org/search?'.http_build_query($q);
        $json = $this->httpGet($url, 6);
        if (!$json) return [null, null];

        $arr = json_decode($json, true);
        if (!is_array($arr) || empty($arr[0])) return [null, null];

        $lat = isset($arr[0]['lat']) ? (float)$arr[0]['lat'] : null;
        $lon = isset($arr[0]['lon']) ? (float)$arr[0]['lon'] : null;
        return [$lat, $lon];
    }

    private function httpGet(string $url, int $timeout = 6): ?string
    {
        $ctx = stream_context_create(['http' => [
            'method'  => 'GET',
            'header'  => "User-Agent: Maintronic-TourPrep/1.0 (contact: support@exemple.fr)\r\nAccept-Language: fr\r\n",
            'timeout' => $timeout,
        ]]);
        $json = @file_get_contents($url, false, $ctx);
        return $json !== false ? $json : null;
    }

    /* ======================= UTILITAIRES TEXTE ======================= */

    private function label(string $ad, string $cp, string $ville): string
    {
        $parts = [];
        if ($ad   !== '') $parts[] = $ad;
        if ($cp   !== '' || $ville !== '') $parts[] = trim($cp.' '.$ville);
        return implode(', ', $parts);
    }

    private function cleanStreet(string $s): string
    {
        $s = (string)$s;
        $s = str_replace(["\r","\n","\t"], ' ', $s);
        $s = preg_replace('/\s{2,}/', ' ', $s);
        $s = preg_replace('~\b(BP|CEDEX|CS)\b[^\d]*\d*~i', '', $s);
        $s = preg_replace('~\b(ENTREP[ÔO]T|D[ÉE]P[ÔO]T|SERVICE|MAGASIN|SOC(IETE)?|SARL|SAS|SA)\b.*$~i', '', $s);
        $s = preg_replace('~\bAV(ENUE)?\b~i', 'Avenue', $s);
        $s = preg_replace('~\bBD\b~i', 'Boulevard', $s);
        $s = preg_replace('~\bRTE\b~i', 'Route', $s);
        $s = preg_replace('~\bCHEM(IN)?\b~i', 'Chemin', $s);
        $s = preg_replace('~\bPL\b~i', 'Place', $s);
        return trim(preg_replace('/\s+/', ' ', $s));
    }

    private function cleanCity(string $s): string
    {
        $s = (string)$s;
        $s = preg_replace('~\bCEDEX\b.*$~i', '', $s);
        return trim(preg_replace('/\s+/', ' ', $s));
    }

    private function cleanCp(string $s): string
    {
        $s = trim((string)$s);
        if (preg_match('~\b(\d{5})\b~', $s, $m)) return $m[1];
        return '';
    }
}
