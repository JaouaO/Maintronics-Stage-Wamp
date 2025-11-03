<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class TraitementDossierService
{
    /** Mémorisation locale pendant la requête */
    private ?Collection $vocabByGroup = null;


    /**
     * Charge toutes les données nécessaires à la vue d’édition.
     * @param mixed $numInt
     * @param array|null $agencesAutorisees
     * @param string $tz
     * @return array
     */
    public function loadEditPayload($numInt, ?array $agencesAutorisees = null, $tz = 'Europe/Paris'): array
    {
        // 0) Intervention
        $interv = DB::table('t_intervention')->where('NumInt', $numInt)->first();
        if (!$interv) {
            return ['interv' => null];
        }


        // 2) Vocabulaire (mémo en propriété)
        $vocabByGroup = $this->getVocabByGroup();

        // 3) État (bits + champs libres)
        $etat = DB::table('t_actions_etat')
            ->select('bits_traitement','bits_affectation','objet_traitement','contact_reel')
            ->where('NumInt', $numInt)
            ->first();

        $bitsTrait   = $etat->bits_traitement  ?? '';
        $objetTrait  = $etat->objet_traitement ?? '';
        $contactReel = $etat->contact_reel     ?? '';

        $isBitOn = static function (string $bits, int $i): bool {
            return isset($bits[$i]) && $bits[$i] === '1';
        };

        // 4) Items
        $traitementItems = [];
        foreach (($vocabByGroup->get('TRAITEMENT') ?: collect()) as $row) {
            $traitementItems[] = [
                'code'      => $row->code,
                'pos_index' => (int) $row->pos_index,
                'label'     => $row->label,
                'checked'   => $isBitOn($bitsTrait, (int) $row->pos_index),
            ];
        }

        $affectationItems = [];
        foreach (($vocabByGroup->get('AFFECTATION') ?: collect()) as $row) {
            $affectationItems[] = [
                'code'      => $row->code,
                'pos_index' => (int) $row->pos_index,
                'label'     => $row->label,
                'checked'   => false, // toujours décoché
            ];
        }

        $labelsOnArr = static function(array $items): array {
            $out = [];
            foreach ($items as $it) {
                if (!empty($it['checked'])) $out[] = $it['label'];
            }
            return $out;
        };

        $traitementList  = $labelsOnArr($traitementItems);


// $affectationTexte actuel (ex: "Affaires, Commande de pièce")
        $affectationTexte = trim((string)($etat->objet_traitement ?? ''));
        $affectationList  = array_values(array_filter(array_map('trim', explode(',', $affectationTexte))));

// (si tu gardes aussi les versions "texte")
        $traitementTexte = implode(', ', $traitementList);


        // 5) Agences autorisées
        $agences = $this->resolveAgencesAutorisees($agencesAutorisees, $interv);

        // 6) Salariés & Techniciens — 1 requête, partition en PHP
        $people = DB::table('t_salarie')
            ->when(!empty($agences), function ($q) use ($agences) { $q->whereIn('CodeAgSal', $agences); })
            ->select('CodeSal','NomSal','CodeAgSal','fonction','LibFonction')
            ->orderBy('NomSal')
            ->limit(500)
            ->get();

        list($techniciens, $salaries) = $people->partition(function ($p) {
            $f1 = strtoupper((string) ($p->fonction ?? ''));
            $f2 = strtoupper((string) ($p->LibFonction ?? ''));
            // équivalent de str_starts_with(..., 'TECH') en 7.4
            return (strpos($f1, 'TECH') === 0) || (strpos($f2, 'TECH') === 0);
        });

        // 7) Historique
        $suivis = DB::table('t_suiviclient_histo')
            ->where('NumInt', $numInt)
            ->select([
                'id',              // <— ID primaire (assurez-vous que la colonne s’appelle bien "id")
                'NumInt',
                'CreatedAt',
                'CodeSalAuteur',
                'Titre',
                'Texte',
                'evt_type',  // NEW
                'evt_meta',  // NEW
            ])
            ->orderByDesc('CreatedAt')
            ->orderByDesc('id')
            ->limit(200)
            ->get();


        // 8) Horloge + infos planif
        $serverNow = Carbon::now($tz)->toIso8601String();

        $techCode = $interv->CodeTech ?? '';
        $techDate = (!empty($interv->DateIntPrevu) && $interv->DateIntPrevu !== '0000-00-00') ? $interv->DateIntPrevu : '';
        $techTime = (!empty($interv->HeureIntPrevu) && $interv->HeureIntPrevu !== '00:00:00') ? substr($interv->HeureIntPrevu, 0, 5) : '';


        return [
            'interv'            => $interv,
            'traitementItems'   => $traitementItems,
            'affectationItems'  => $affectationItems,
            'traitementTexte'  => $traitementTexte,   // déjà proposé
            'affectationTexte' => $affectationTexte,  // déjà proposé
            'traitementList'   => $traitementList,    // NEW (array)
            'affectationList'  => $affectationList,   // NEW (array)
            'objetTrait'        => $objetTrait,
            'contactReel'       => $contactReel,
            'salaries'          => $salaries,
            'techniciens'       => $techniciens,
            'suivis'            => $suivis,
            'serverNow'         => $serverNow,
            'techCode'          => $techCode,
            'techDate'          => $techDate,
            'techTime'          => $techTime,
        ];
    }

    /** Charge et groupe le vocabulaire une seule fois pendant la requête. */
    private function getVocabByGroup(): Collection
    {
        if ($this->vocabByGroup instanceof Collection) {
            return $this->vocabByGroup;
        }

        $this->vocabByGroup = DB::table('t_actions_vocabulaire')
            ->orderBy('group_code')
            ->orderBy('pos_index')
            ->get()
            ->groupBy('group_code');

        return $this->vocabByGroup;
    }

    private function resolveAgencesAutorisees(?array $incoming, $interv): array
    {
        if (is_array($incoming) && !empty($incoming)) {
            return $incoming;
        }
        $global = isset($GLOBALS['agences_autorisees']) ? $GLOBALS['agences_autorisees'] : (view()->shared('agences_autorisees') ?: []);
        if (is_array($global) && !empty($global)) {
            return $global;
        }
        $fallback = isset($interv->AgTrf) ? $interv->AgTrf : (isset($GLOBALS['data']->CodeAgSal) ? $GLOBALS['data']->CodeAgSal : null);
        return $fallback ? [$fallback] : [];
    }


}
