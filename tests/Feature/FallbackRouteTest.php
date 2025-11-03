<?php

namespace Tests\Feature;

use Tests\TestCase;

class FallbackRouteTest extends TestCase
{
    public function test_fallback_redirects_to_error_with_message(): void
    {
        $resp = $this->get('/__inconnue__');
        $resp->assertStatus(302)
            ->assertRedirect(route('erreur'));

        // On suit la redirection pour vérifier le message
        $this->followRedirects($resp)->assertSee('Page introuvable.');
    }
}
