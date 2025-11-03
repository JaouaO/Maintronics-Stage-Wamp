<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Config;

class SecurityAndSessionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Deux routes "de labo" pour isoler les middlewares :
        Route::middleware(['web', 'security.headers'])->get('/_test/open-ok', function () {
            return response('OPEN', 200);
        });

        Route::middleware(['web', 'security.headers', 'check.session'])->get('/_test/protected-ok', function () {
            return response('OK', 200);
        });
    }

    /** 1) La page de login répond */
    public function test_login_is_reachable(): void
    {
        $this->get('/authentification')->assertStatus(200);
    }

    /** 2) Un accès protégé redirige sans session */
    public function test_protected_requires_session(): void
    {
        $this->get('/_test/protected-ok')
            ->assertRedirect('/authentification');
    }

    /** 3) En LOCAL, la CSP est en Report-Only (pas besoin de route protégée pour ce test) */
    public function test_local_env_has_csp_report_only_on_open_route(): void
    {
        Config::set('app.env', 'local');
        Config::set('app.debug', true);

        $this->get('/_test/open-ok')
            ->assertOk()
            ->assertHeader('Content-Security-Policy-Report-Only');
    }

    /** 4) En PROD + HTTPS, CSP bloquante + HSTS (route ouverte pour isoler les headers) */
    public function test_prod_https_has_csp_and_hsts_on_open_route(): void
    {
        Config::set('app.env', 'production');
        Config::set('app.debug', false);

        $this->get('/_test/open-ok', ['X-Forwarded-Proto' => 'https'])
            ->assertOk()
            ->assertHeader('Content-Security-Policy')
            ->assertHeader('Strict-Transport-Security', 'max-age=15552000; includeSubDomains; preload');
    }

    /** 5) Logout GET : vide la session puis rechute sur le login */
    public function test_logout_clears_session(): void
    {
        $this->withSession(['id' => 'DEDA'])
            ->get('/deconnexion')
            ->assertRedirect('/authentification');

        // Re-tester la route protégée : on doit être renvoyé vers /authentification
        $this->get('/_test/protected-ok')
            ->assertRedirect('/authentification');
    }
}
