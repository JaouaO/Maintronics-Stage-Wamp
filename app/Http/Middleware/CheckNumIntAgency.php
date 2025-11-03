<?php

namespace App\Http\Middleware;

use App\Services\AccessInterventionService;
use Closure;
use Illuminate\Http\Request;

class CheckNumIntAgency
{
    private AccessInterventionService $access;

    public function __construct(AccessInterventionService $access)
    {
        $this->access = $access;
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $paramName  Nom du paramètre de route (par défaut: numInt)
     */
    public function handle(Request $request, Closure $next, string $paramName = 'numInt')
    {
        // Récupère le paramètre de route (numéro d’intervention)
        $numInt = (string) ($request->route($paramName) ?? '');
        if ($numInt === '') {
            abort(400, 'Paramètre manquant.');
        }

        // Agences autorisées (session)
        $agencesAutorisees = array_map('strtoupper', (array) $request->session()->get('agences_autorisees', []));

        // Préfixe agence depuis le NumInt (ex: "M06A-2510-00003" -> "M06A")
        $agFromNum = strtoupper($this->access->agenceFromNumInt($numInt));

        if ($agFromNum === '' || !in_array($agFromNum, $agencesAutorisees, true)) {
            abort(403, 'Accès interdit à cette intervention.');
        }

        return $next($request);
    }
}
