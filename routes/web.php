<?php

use App\Http\Controllers\MainController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn() => redirect()->route('authentification'));

// Auth
Route::get('/authentification', [MainController::class, 'showLoginForm'])
    ->name('authentification')
    ->middleware('throttle:30,1');            // Form login limité

Route::post('/authentification', [MainController::class, 'login'])
    ->name('authentification.post')
    ->middleware('throttle:10,1');            // Tentatives login limitées

// Page d'erreur (utilisée par fallback)
Route::get('/erreur', function () {
    $message = session('message', 'Une erreur est survenue.');
    return view('error', ['message' => $message]);
})->name('erreur');

// Déconnexion
Route::get('/deconnexion', function () {
    session()->flush();
    return redirect()->route('authentification');
})->name('deconnexion');



// Contraintes de paramètres
$NUMINT_RE = '^(?!(nouvelle|suggest-num)$)[A-Za-z0-9_-]+$';
$CODETECH_RE  = '^[A-Za-z0-9_-]{2,10}$';

// Groupe protégé : session + headers sécurité
Route::middleware(['check.session', 'security.headers'])->group(function () use ($NUMINT_RE, $CODETECH_RE) {

    // UI (lecture)
    Route::get('/interventions', [MainController::class, 'showInterventions'])
        ->name('interventions.show')
        ->middleware('throttle:120,1');

    Route::get('/interventions/nouvelle', [MainController::class, 'createIntervention'])
        ->name('interventions.create')
        ->middleware('throttle:60,1');

    Route::get('/interventions/{numInt}', [MainController::class, 'editIntervention'])
        ->name('interventions.edit')
        ->where('numInt', $NUMINT_RE)
        ->middleware(['check.numint', 'throttle:120,1']);

    Route::get('/interventions/{numInt}/history', [MainController::class, 'history'])
        ->name('interventions.history')
        ->where('numInt', $NUMINT_RE)
        ->middleware(['check.numint', 'throttle:120,1']);

    // API internes / écritures (throttle plus strict)
    Route::get('/interventions/suggest-num', [MainController::class, 'suggestNumInt'])
        ->name('interventions.suggest')
        ->middleware('throttle:60,1');

    Route::post('/interventions', [MainController::class, 'storeIntervention'])
        ->name('interventions.store')
        ->middleware('throttle:30,1');

    Route::post('/interventions/update/{numInt}', [MainController::class, 'updateIntervention'])
        ->name('interventions.update')
        ->where('numInt', $NUMINT_RE)
        ->middleware(['check.numint', 'throttle:30,1']);

    Route::post('/interventions/{numInt}/rdv/temporaire', [MainController::class, 'rdvTemporaire'])
        ->name('interventions.rdv.temporaire')
        ->where('numInt', $NUMINT_RE)
        ->middleware(['check.numint', 'throttle:30,1']);

    Route::post('/interventions/{numInt}/rdv/temporaire/check', [MainController::class, 'rdvTempCheck'])
        ->name('rdv.temp.check')
        ->where('numInt', $NUMINT_RE)
        ->middleware(['check.numint', 'throttle:60,1']);

    Route::post('/interventions/{numInt}/rdv/temporaire/purge', [MainController::class, 'rdvTempPurge'])
        ->name('rdv.temp.purge')
        ->where('numInt', $NUMINT_RE)
        ->middleware(['check.numint', 'throttle:15,1']);

    Route::delete('/interventions/{numInt}/rdv/temporaire/{id}', [MainController::class, 'rdvTempDelete'])
        ->name('rdv.temp.delete')
        ->where(['numInt' => $NUMINT_RE, 'id' => '^[0-9]+$'])
        ->middleware(['check.numint', 'throttle:30,1']);

    Route::get('/api/planning/{codeTech}', [MainController::class, 'apiPlanningTech'])
        ->name('api.planning.tech')
        ->where('codeTech', $CODETECH_RE)
        ->middleware('throttle:60,1');
});

// Fallback propre
Route::fallback(fn() => redirect()->route('erreur')->with('message', 'Page introuvable.'));
