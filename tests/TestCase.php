<?php

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ninguna prueba sale a la red (Módulo 6, FR-018): toda llamada HTTP debe estar simulada con `Http::fake()`.
        Http::preventStrayRequests();
    }

    /**
     * `auth.session` (R-05) guarda en la sesión el hash de la contraseña del usuario autenticado; al cambiar de usuario
     * dentro de una misma prueba hay que olvidarlo, o la segunda solicitud se tomaría como una sesión invalidada.
     */
    public function actingAs(Authenticatable $user, $guard = null)
    {
        $this->app['session.store']->forget('password_hash_'.($guard ?? 'web'));

        return parent::actingAs($user, $guard);
    }
}
