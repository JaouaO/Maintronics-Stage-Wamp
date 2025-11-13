<?php

use App\Http\Controllers\MainController;
use App\Http\Controllers\TourPrepController;
use Illuminate\Support\Facades\Route;

// Page d’entrée : redirige vers l’authentification
Route::get('/', fn() => redirect()->route('authentification'));

// =========================
// Authentification / Session
// =========================

// Formulaire de login
Route::get('/authentification', [MainController::class, 'showLoginForm'])
    ->name('authentification')
    ->middleware('throttle:130,1'); // Form login limité

// Soumission du login
Route::post('/authentification', [MainController::class, 'login'])
    ->name('authentification.post')
    ->middleware('throttle:110,1'); // Tentatives login limitées

// Page d’erreur générique
Route::get('/erreur', function () {
    $message = session('message', 'Une erreur est survenue.');
    return view('error', ['message' => $message]);
})->name('erreur');

// Déconnexion : purge la session et renvoie vers login
Route::get('/deconnexion', function () {
    session()->flush();
    return redirect()->route('authentification');
})->name('deconnexion');

// =========================
// Contraintes de paramètres
// =========================

// NumInt : exclut explicitement les slugs réservés ("nouvelle", "suggest-num")
$NUMINT_RE   = '^(?!(nouvelle|suggest-num)$)[A-Za-z0-9_-]+$';
$CODETECH_RE = '^[A-Za-z0-9_-]{2,10}$';

// =========================
// Zone protégée : nécessite une session valide
// et applique des headers de sécurité
// =========================

Route::middleware(['check.session', 'security.headers'])->group(function () use ($NUMINT_RE, $CODETECH_RE) {

    // -------- UI INTERVENTIONS --------

    // Tableau des interventions (listing principal)
    Route::get('/interventions', [MainController::class, 'showInterventions'])
        ->name('interventions.show')
        ->middleware('throttle:220,1');

    // Formulaire "nouvelle intervention"
    Route::get('/interventions/nouvelle', [MainController::class, 'createIntervention'])
        ->name('interventions.create')
        ->middleware('throttle:160,1');

    // Fiche intervention (édition)
    Route::get('/interventions/{numInt}', [MainController::class, 'editIntervention'])
        ->name('interventions.edit')
        ->where('numInt', $NUMINT_RE)
        ->middleware(['check.numint', 'throttle:220,1']);

    // Historique d’une intervention (popup)
    Route::get('/interventions/{numInt}/history', [MainController::class, 'history'])
        ->name('interventions.history')
        ->where('numInt', $NUMINT_RE)
        ->middleware(['check.numint', 'throttle:220,1']);

    // -------- API INTERVENTIONS / ÉCRITURES --------

    // Suggestion de NumInt (AJAX)
    Route::get('/interventions/suggest-num', [MainController::class, 'suggestNumInt'])
        ->name('interventions.suggest')
        ->middleware('throttle:160,1');

    // Création d’une intervention
    Route::post('/interventions', [MainController::class, 'storeIntervention'])
        ->name('interventions.store')
        ->middleware('throttle:130,1');

    // Mise à jour globale (form principal)
    Route::post('/interventions/update/{numInt}', [MainController::class, 'updateIntervention'])
        ->name('interventions.update')
        ->where('numInt', $NUMINT_RE)
        ->middleware(['check.numint', 'throttle:130,1']);

    // Ajout / remplacement d’un RDV temporaire (AJAX)
    Route::post('/interventions/{numInt}/rdv/temporaire', [MainController::class, 'rdvTemporaire'])
        ->name('interventions.rdv.temporaire')
        ->where('numInt', $NUMINT_RE)
        ->middleware(['check.numint', 'throttle:130,1']);

    // Suppression d’un RDV temporaire (AJAX)
    Route::delete('/interventions/{numInt}/rdv/temporaire/{id}', [MainController::class, 'rdvTempDelete'])
        ->name('rdv.temp.delete')
        ->where([
            'numInt' => $NUMINT_RE,
            'id'     => '^[0-9]+$',
        ])
        ->middleware(['check.numint', 'throttle:130,1']);

    // -------- API PLANNING --------

    // Planning technicien (JSON) pour l’agenda
    Route::get('/api/planning/{codeTech}', [MainController::class, 'apiPlanningTech'])
        ->name('api.planning.tech')
        ->where('codeTech', $CODETECH_RE)
        ->middleware('throttle:160,1');

    // Replanification fine via modale tournée (AJAX)
    Route::post('/interventions/{numInt}/replanifier', [MainController::class, 'replanifierAjax'])
        ->name('tournee.replanifier')
        ->where('numInt', $NUMINT_RE)
        ->middleware(['check.numint', 'throttle:130,1']);

    // -------- AUTOPLANNING --------

    // Génération de proposition d’autoplanning (sans commit)
    Route::post('/tourprep/autoplan/generate', [TourPrepController::class, 'autoplanGenerate'])
        ->name('tourprep.autoplan.generate');

    // Commit des affectations issues de l’autoplanning
    Route::post('/tourprep/autoplan/commit', [TourPrepController::class, 'autoplanCommit'])
        ->name('tourprep.autoplan.commit');
});

// =========================
// Tournée : page carte publique (lecture seule)
// =========================

Route::get('/tournee', [TourPrepController::class, 'show'])
    ->name('tournee.show');

// =========================
// Fallback global
// =========================

Route::fallback(
    fn() => redirect()
        ->route('erreur')
        ->with('message', 'Page introuvable.')
);
