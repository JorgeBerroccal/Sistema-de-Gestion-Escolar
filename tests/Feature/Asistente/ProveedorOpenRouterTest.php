<?php

use App\Asistente\Proveedores\ProveedorNoDisponible;
use App\Asistente\Proveedores\ProveedorOpenRouter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const MENSAJES = [
    ['rol' => 'system', 'texto' => 'instrucciones'],
    ['rol' => 'system', 'texto' => 'contexto'],
    ['rol' => 'user', 'texto' => 'hola'],
];

function openRouter(array $c = []): ProveedorOpenRouter
{
    config(['asistente.openrouter' => array_merge([
        'clave' => 'clave-de-prueba', 'url' => 'https://openrouter.test/api/v1', 'modelo' => 'proveedor/modelo:free',
        'respaldo' => [], 'datos_reales' => false, 'timeout' => 5,
    ], $c)]);

    return ProveedorOpenRouter::desdeConfiguracion();
}

function respuestaOk(string $texto = 'Hola, esto es una respuesta.', string $modelo = 'proveedor/modelo:free'): array
{
    return ['choices' => [['message' => ['role' => 'assistant', 'content' => $texto]]], 'model' => $modelo];
}

it('envía la solicitud con el formato compatible con OpenAI y devuelve el texto y el modelo', function () {
    Http::fake(['openrouter.test/*' => Http::response(respuestaOk('Tu promedio es 13.', 'proveedor/otro:free'))]);

    $r = openRouter()->responder(MENSAJES);

    expect($r->texto)->toBe('Tu promedio es 13.')->and($r->modelo)->toBe('proveedor/otro:free');

    Http::assertSent(function (Request $q) {
        return $q->url() === 'https://openrouter.test/api/v1/chat/completions'
            && $q->hasHeader('Authorization', 'Bearer clave-de-prueba')
            && $q['model'] === 'proveedor/modelo:free'
            && ! isset($q['models'])
            && $q['messages'] === [
                ['role' => 'system', 'content' => 'instrucciones'],
                ['role' => 'system', 'content' => 'contexto'],
                ['role' => 'user', 'content' => 'hola'],
            ];
    });
    Http::assertSentCount(1);
});

it('envía los modelos de respaldo en una sola solicitud', function () {
    Http::fake(['openrouter.test/*' => Http::response(respuestaOk())]);

    openRouter(['modelo' => 'a/uno:free', 'respaldo' => ['b/dos:free', 'c/tres:free']])->responder(MENSAJES);

    Http::assertSent(fn (Request $q) => $q['models'] === ['a/uno:free', 'b/dos:free', 'c/tres:free'] && $q['model'] === 'a/uno:free');
    Http::assertSentCount(1);
});

it('convierte cualquier fallo en ProveedorNoDisponible sin filtrar detalles', function (Closure $fake) {
    Http::fake(['openrouter.test/*' => $fake]);

    try {
        openRouter()->responder(MENSAJES);
        $this->fail('Debió lanzar ProveedorNoDisponible');
    } catch (ProveedorNoDisponible $e) {
        expect($e->getMessage())->not->toContain('clave-de-prueba')->not->toContain('hola');
    }
})->with([
    'cuota agotada (429)' => [fn () => Http::response(['error' => ['message' => 'rate limit']], 429)],
    'error del servidor (500)' => [fn () => Http::response('boom', 500)],
    'sin permiso (401)' => [fn () => Http::response(['error' => 'x'], 401)],
    'error con 200' => [fn () => Http::response(['error' => ['message' => 'sin modelos'], 'choices' => []], 200)],
    'sin texto' => [fn () => Http::response(['choices' => [['message' => ['content' => '  ']]]], 200)],
    'sin choices' => [fn () => Http::response(['id' => 'x'], 200)],
    'cuerpo no JSON' => [fn () => Http::response('<html>', 200)],
    'tiempo agotado' => [fn () => throw new ConnectionException('timeout')],
]);

it('con configuración incompleta no llama al proveedor', function (array $c) {
    Http::fake();

    expect(fn () => openRouter($c)->responder(MENSAJES))->toThrow(ProveedorNoDisponible::class);
    Http::assertNothingSent();
})->with([
    'sin clave' => [['clave' => null]],
    'sin modelo' => [['modelo' => null]],
]);

it('declara no admitir datos reales con modelos gratuitos o sin la declaración explícita', function () {
    expect(openRouter(['modelo' => 'a/uno:free', 'datos_reales' => true])->admiteDatosReales())->toBeFalse()
        ->and(openRouter(['modelo' => 'a/pago', 'respaldo' => ['b/dos:free'], 'datos_reales' => true])->admiteDatosReales())->toBeFalse()
        ->and(openRouter(['modelo' => 'a/pago', 'respaldo' => [], 'datos_reales' => false])->admiteDatosReales())->toBeFalse()
        ->and(openRouter(['modelo' => 'anthropic/claude-haiku', 'datos_reales' => true])->admiteDatosReales())->toBeTrue()
        ->and(openRouter()->nombre())->toBe('openrouter');
});
