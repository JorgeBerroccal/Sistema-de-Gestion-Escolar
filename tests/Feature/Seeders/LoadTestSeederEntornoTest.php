<?php

use App\Models\Usuario;
use Database\Seeders\LoadTestSeeder;

// FR-020: la siembra de usuarios de carga solo se permite en local, staging y testing.

it('se ejecuta en el entorno :dataset', function (string $entorno) {
    app()->detectEnvironment(fn () => $entorno);

    (new LoadTestSeeder)->run();

    expect(Usuario::where('username', 'like', 'carga%')->count())->toBe(LoadTestSeeder::TOTAL);
})->with(['local', 'staging', 'testing']);

it('se rechaza en el entorno :dataset sin crear usuarios', function (string $entorno) {
    app()->detectEnvironment(fn () => $entorno);

    expect(fn () => (new LoadTestSeeder)->run())
        ->toThrow(RuntimeException::class, $entorno);

    expect(Usuario::where('username', 'like', 'carga%')->count())->toBe(0);
})->with(['production', 'qa', 'prod']);
