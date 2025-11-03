<?php

namespace Tests\Feature;

use Tests\Feature\Support\LoggedInTestCase;
use Illuminate\Support\Facades\Route;

class CheckSessionBehaviorTest extends LoggedInTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Deux routes factices protégées par CheckSession (et pas par vos contrôleurs)
        Route::middleware(['web','check.session'])
            ->get('/__probe/html', fn() => response('ok', 200));
        Route::middleware(['web','check.session'])
            ->get('/__probe/json', fn() => response()->json(['ok' => true]));
        Route::middleware(['web','check.session'])
            ->post('/__probe/post', fn() => response('posted', 200));

        app('router')->getRoutes()->refreshNameLookups();
    }


    public function test_json_get_no_redirect(): void
    {
        // Pour JSON/AJAX, pas de redirection ajoutant ?id
        $this->getJson('/__probe/json')->assertOk()->assertJson(['ok'=>true]);
    }


}
