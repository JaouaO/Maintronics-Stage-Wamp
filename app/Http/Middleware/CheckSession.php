<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\CheckAutorisationsService;

class CheckSession
{

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */

    public function handle(Request $request, Closure $next)
    {
        if (!session()->has('id')) {
            return redirect()->route('authentification')
                ->with('message', 'Vous devez être connecté pour accéder à cette page.');
        }

        $idFromUrl = $request->query('id');
        if ($idFromUrl && !preg_match('/^[A-Za-z0-9_-]+$/', $idFromUrl)) {
            abort(400, 'ID invalide (caractères non autorisés).');
        }
        $sessionId = (string)session('id');

        // Ne fait l’affichage de ?id=... que pour les requêtes HTML (pas JSON/AJAX)
        $isHtmlGet = $request->isMethod('GET') && !$request->expectsJson() && !$request->ajax();

        // (B1) GET + id manquant → on ajoute ?id=<session>
        if ($isHtmlGet && !$idFromUrl) {
            $url = $request->fullUrlWithQuery(['id' => $sessionId]);
            return redirect()->to($url);
        }

        // (B2) GET + id présent mais différent → on remplace par l’id de session
        if ($isHtmlGet && $idFromUrl && $idFromUrl !== $sessionId) {
            $url = $request->fullUrlWithQuery(['id' => $sessionId]);
            return redirect()->to($url);
        }

        // (C) POST + id fourni mais ≠ session → rejet
        if ($request->isMethod('POST')) {
            $postedId = $request->input('id');
            if ($postedId && $postedId !== $sessionId) {
                abort(403, 'ID invalide.');
            }
        }

        // --- Autorisations horaires & agence ---
        $ip = $request->ip();
        $response = app(\App\Services\CheckAutorisationsService::class)->checkAutorisations($sessionId, $ip);
        if (!$response['success']) {
            abort(403, 'Accès non autorisé');
        }
        $data = $response['data'];

        view()->share('data', $data);
        view()->share('agences_autorisees', $data->agences_autorisees ?? []);
        view()->share('defaultAgence', $data->defaultAgence ?? null);

        session([
            'agences_autorisees' => (array)($data->agences_autorisees ?? []),
            'codeAg' => (string)($data->CodeAgSal ?? ''),
            'codeSal' => (string)($data->CodeSal ?? ($data->Util ?? '')),
            'defaultAgence' => (string)($data->defaultAgence ?? ''),
        ]);

        return $next($request);
    }
}
