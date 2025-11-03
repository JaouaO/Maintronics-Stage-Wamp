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
        $sessionId = session('id');



        if ($request->isMethod('GET') && $idFromUrl && $idFromUrl !== $sessionId) {
            $flashSuccess = session('success'); // récupère le flash actuel
            $params = array_merge($request->route()->parameters(), ['id' => $sessionId]);

            return redirect()->route($request->route()->getName(), $params)
                ->with('success', $flashSuccess);
        }

        if ($request->isMethod('POST')) {
            $postedId = $request->input('id');
            if ($postedId && $postedId !== $sessionId) {
                abort(403, 'ID invalide.');
            }
        }


        $ip = $request->ip();

        $response = app(CheckAutorisationsService::class)
            ->checkAutorisations($sessionId, $ip);

        if (!$response['success']) {
            abort(403, 'Accès non autorisé');
        }


        $data = $response['data'];

        // Partager la ligne DB complète avec Blade
        view()->share('data', $data);
        view()->share('agences_autorisees', $data->agences_autorisees ?? []);

        // Sauvegarder le CodeSal dans la session
        session(['codeSal' => $data->Code_Sal]);


        return $next($request);

    }

}
