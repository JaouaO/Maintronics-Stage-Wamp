<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use IntlDateFormatter;

class CheckAutorisationsService
{
    /**
     * Vérifie si un utilisateur a le droit d'accès selon son ID et les horaires.
     *
     * @param string|int $id
     * @return @return array           ['success' => bool, 'data' => objet|null]
     */
    public function checkAutorisations($id, $ipClient): array
    {
        $data = $this->getData($id);
        if (!$data) {
            return ['success' => false, 'data' => null];
        }

        $today = Carbon::now('Europe/Paris')->toDateString();
        // Vérifie que la date de t_log_util est bien aujourd'hui et l'IP correspond
        if ($data->DateAcces !== $today || $data->IP !== $ipClient) {
            return ['success' => false, 'data' => null];
        }

        $jour = $this->getJourCourant();
        $now  = Carbon::now('Europe/Paris')->format('H:i:s');

        if($this->isHoraireOk($data, $jour, $now)){
            return ['success' => true, 'data' => $data];
        }else{
            return ['success' => false, 'data' => null];
        }

    }

    /**
     * Récupère toutes les données nécessaires pour le check.
     */
    private function getData($id)
    {
        $today = Carbon::now('Europe/Paris')->toDateString();

        $data = DB::table('t_log_util as l')
            ->select([
                'l.id', 'l.IP', 'l.DateAcces', 'l.Demat',
                's.NomSal', 's.CodeAgSal', 's.CodeSal',
                's.automenu1','s.automenu2','s.automenu3','s.automenu4',
                's.automenu5','s.automenu6','s.automenu7','s.automenu8',
                's.automenu9','s.automenu10','s.automenu11','s.automenu12',
                'h.*',
                'he.Date1','he.Date2',
                'he.HoraireJour1','he.HoraireJour2','he.HoraireJour3','he.HoraireJour4',
            ])
            ->leftJoin('t_salarie as s', 's.CodeSal', '=', 'l.Util')
            ->leftJoin('t_horaire as h', 'h.Code_Sal', '=', 'l.Util')
            ->leftJoin('t_horaireexcept as he', function($join) use ($today) {
                $join->on('he.Code_Sal', '=', 'l.Util')
                    ->where(function($q) use ($today) {
                        $q->where('he.Date1', '=', $today)
                            ->orWhere('he.Date2', '=', $today);
                    });
            })
            ->where('l.id', '=', $id)
            ->first();

        if (!$data) return null;

        // Agences autorisées (ADMI / PLUS / DOAG / agence unique)
        $codeAg  = $data->CodeAgSal ?? null;
        $codeSal = $data->CodeSal   ?? $data->Util ?? null;

        if (!$codeAg || !$codeSal) {
            $data->agences_autorisees = [];
            return $data;
        }

        if ($codeAg === 'ADMI') {
            $data->agences_autorisees = DB::table('agence')->pluck('Code_ag')->all();
        } elseif ($codeAg === 'PLUS') {
            $data->agences_autorisees = DB::table('t_resp')
                ->where('CodeSal', $codeSal)
                ->pluck('CodeAgSal')->unique()->values()->all();
        } elseif ($codeAg === 'DOAG') {
            $data->agences_autorisees = DB::table('agence')
                ->where(function ($q) {
                    $q->where('Code_ag', 'like', 'M%')
                        ->orWhere('Code_ag', 'like', 'C%');
                })
                ->pluck('Code_ag')->all();
        } else {
            $data->agences_autorisees = [$codeAg];
        }

        $data->defaultAgence = $this->computeDefaultAgence(
            $data->agences_autorisees,  // array
            $codeAg,                    // CodeAgSal
            $codeSal                    // CodeSal
        );
        return $data;
    }


    /**
     * Retourne le jour courant au format "Lu", "Ma", "Me", etc.
     */
    private function getJourCourant()
    {
        $fmt = new IntlDateFormatter(
            'fr_FR',
            IntlDateFormatter::FULL,
            IntlDateFormatter::NONE,
            'Europe/Paris',
            IntlDateFormatter::GREGORIAN,
            'EEEE'
        );
        $jour = $fmt->format(Carbon::now('Europe/Paris'));
        return ucfirst(substr($jour, 0, 2));
    }

    /**
     * Vérifie si l'heure actuelle correspond à une plage autorisée (normale ou exceptionnelle),
     * en tenant compte des plages qui traversent minuit.
     */
    private function isHoraireOk($data, $jour, $now)
    {
        $plages = [
            // Plages normales (AM/PM)
            [$data->{$jour . '1'} ?? null, $data->{$jour . '2'} ?? null],
            [$data->{$jour . '3'} ?? null, $data->{$jour . '4'} ?? null],
            // Plages exceptionnelles (AM/PM)
            [$data->HoraireJour1 ?? null, $data->HoraireJour2 ?? null],
            [$data->HoraireJour3 ?? null, $data->HoraireJour4 ?? null],
        ];

        foreach ($plages as [$start, $end]) {
            if (!$start || !$end) continue;

            if ($start < $end) {
                if ($now > $start && $now < $end) return true;
            } else {
                // traverse minuit
                if ($now > $start || $now < $end) return true;
            }
        }

        return false;
    }
    /**
     * Calcule l'agence par défaut pour la session utilisateur.
     * Règle :
     * - Si CodeAgSal ∈ {ADMI, PLUS, DOAG} :
     *     -> on cherche t_resp.Defaut='O' pour ce CodeSal ; si trouvé et autorisé, on prend
     *     -> sinon, première agence autorisée par ordre alphabétique
     * - Sinon :
     *     -> si CodeAgSal ∈ agences autorisées, on prend CodeAgSal
     *     -> sinon, première par ordre alphabétique
     */
    private function computeDefaultAgence(array $agencesAutorisees, ?string $codeAg, ?string $codeSal): ?string
    {
        // Normalisation
        $agences = array_values(array_unique(array_filter($agencesAutorisees, fn($x)=>is_string($x) && $x!=='')));
        if (empty($agences)) {
            return null;
        }

        $isSuper = in_array($codeAg, ['ADMI','PLUS','DOAG'], true);

        if ($isSuper && $codeSal) {
            $pref = \Illuminate\Support\Facades\DB::table('t_resp')
                ->where('CodeSal', $codeSal)
                ->where('Defaut', 'O')
                ->value('CodeAgSal');

            if ($pref && in_array($pref, $agences, true)) {
                return $pref;
            }

            // Pas de Defaut='O' valide → alpha
            $sorted = $agences; sort($sorted, SORT_NATURAL | SORT_FLAG_CASE);
            return $sorted[0] ?? null;
        }

        // Utilisateur "standard"
        if ($codeAg && in_array($codeAg, $agences, true)) {
            return $codeAg;
        }

        $sorted = $agences; sort($sorted, SORT_NATURAL | SORT_FLAG_CASE);
        return $sorted[0] ?? null;
    }

}
