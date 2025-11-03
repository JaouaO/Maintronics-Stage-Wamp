<?php

namespace Tests\Feature;

use Tests\TestCase;

class EnvSanityTest extends TestCase
{
    public function test_env_is_testing_and_app_key_present(): void
    {
        $this->assertTrue(app()->environment('testing'), 'APP_ENV should be testing');
        $this->assertNotEmpty(config('app.key'), 'APP_KEY must be set in .env.testing');
    }
}
