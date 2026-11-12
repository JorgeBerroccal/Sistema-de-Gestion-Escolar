<?php

namespace Tests;

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
}
