<?php

use App\Http\Controllers\MainController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return redirect()->route('authentification');
});

Route::get('/authentification', [MainController::class, 'showLoginForm'])
    ->name('authentification');

Route::post('/authentification', [MainController::class, 'login'])->name('authentification.post');


Route::get('/deconnexion', function () {
    session()->flush(); // supprime toutes les données de session
    return redirect()->route('authentification');
})->name('deconnexion');

Route::get('/accueil', [MainController::class, 'accueil'])
    ->name('accueil')->middleware('check.session');

Route::post('/accueil/entree', [MainController::class, 'entree'])
    ->name('accueil.entree')->middleware('check.session');

Route::get('/interventions', [MainController::class, 'showInterventions'])
    ->name('interventions.show')->middleware('check.session');

Route::get('/interventions/{numInt}', [MainController::class, 'editIntervention'])
    ->name('interventions.edit')->middleware('check.session');

Route::post('/interventions/update/{numInt}', [MainController::class, 'updateIntervention'])
    ->name('interventions.update')->middleware('check.session');

Route::post('/interventions/{numInt}/rdv/temporaire',
    [MainController::class,'rdvTemporaire']
)->name('interventions.rdv.temporaire')->middleware('check.session');


Route::post('/interventions/{numInt}/rdv/temporaire/check', [MainController::class, 'rdvTempCheck'])
    ->name('rdv.temp.check');
Route::post('/interventions/{numInt}/rdv/temporaire/purge', [MainController::class, 'rdvTempPurge'])
    ->name('rdv.temp.purge');
Route::delete(
    '/interventions/{numInt}/rdv/temporaire/{id}',
    [MainController::class, 'rdvTempDelete']
)->name('rdv.temp.delete')->middleware('check.session');


Route::get('/erreur', function () {
    $message = session('message', 'Une erreur est survenue.');
    return view('error', ['message' => $message]);
})->name('erreur');


Route::get('/api/planning/{codeTech}', [MainController::class,'apiPlanningTech'])
    ->name('api.planning.tech')->middleware('check.session');

