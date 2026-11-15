<?php

use App\Asistente\AsistenteService;
use App\Asistente\Proveedores\ProveedorFalso;
use App\Asistente\Proveedores\ProveedorIA;
use App\Asistente\Proveedores\ProveedorOpenRouter;
use App\Asistente\ResultadoAsistente as R;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function usarProveedor(string $nombre, array $openrouter = []): void
{
    config([
        'asistente.proveedor' => $nombre,
        'asistente.openrouter' => array_merge([
            'clave' => 'clave-de-prueba', 'url' => 'https://openrouter.test/api/v1', 'modelo' => 'uno/modelo:free',
            'respaldo' => ['dos/modelo:free'], 'datos_reales' => false, 'timeout' => 5,
        ], $openrouter),
    ]);
    app()->forgetInstance(ProveedorIA::class);
    app()->forgetInstance(AsistenteService::class);
}

it('cambiar de adaptador solo por configuración mantiene el funcionamiento', function () {
    $e = escenarioAsistente();
    Http::fake(['openrouter.test/*' => Http::response(['choices' => [['message' => ['content' => 'Respuesta desde OpenRouter.']]], 'model' => 'uno/modelo:free'])]);

    usarProveedor('openrouter');
    expect(app(ProveedorIA::class))->toBeInstanceOf(ProveedorOpenRouter::class)
        ->and(app(AsistenteService::class)->preguntar($e['alumno_usuario'], '¿cómo voy?')->texto)->toBe('Respuesta desde OpenRouter.');

    usarProveedor('falso');
    expect(app(ProveedorIA::class))->toBeInstanceOf(ProveedorFalso::class)
        ->and(app(AsistenteService::class)->preguntar($e['alumno_usuario'], '¿cómo voy?')->texto)->toContain('Respuesta de prueba');
});

it('cambiar el modelo y el respaldo solo por configuración cambia lo que se envía', function () {
    $e = escenarioAsistente();
    Http::fake(['openrouter.test/*' => Http::response(['choices' => [['message' => ['content' => 'ok']]]])]);

    usarProveedor('openrouter', ['modelo' => 'nuevo/modelo:free', 'respaldo' => ['otro/modelo:free', 'ultimo/modelo:free']]);
    app(AsistenteService::class)->preguntar($e['alumno_usuario'], 'hola');

    Http::assertSent(fn (Request $q) => $q['model'] === 'nuevo/modelo:free' && $q['models'] === ['nuevo/modelo:free', 'otro/modelo:free', 'ultimo/modelo:free']);
});

it('con OpenRouter el contexto enviado sigue sin datos personales ni ajenos', function () {
    $e = escenarioAsistente();
    Http::fake(['openrouter.test/*' => Http::response(['choices' => [['message' => ['content' => 'ok']]]])]);
    usarProveedor('openrouter');

    app(AsistenteService::class)->preguntar($e['padre'], 'notas de Ana');

    Http::assertSent(function (Request $q) {
        $texto = collect($q['messages'])->where('role', 'system')->pluck('content')->implode("\n");

        return str_contains($texto, 'Ana Alfa') && ! str_contains($texto, 'Beto') && ! str_contains($texto, '47851236') && ! str_contains($texto, '@');
    });
});

it('con OpenRouter caído o sin cuota el usuario recibe un mensaje comprensible', function () {
    $e = escenarioAsistente();
    Http::fake(['openrouter.test/*' => Http::response(['error' => ['message' => 'Rate limit exceeded: free-models-per-day']], 429)]);
    usarProveedor('openrouter');

    $r = app(AsistenteService::class)->preguntar($e['alumno_usuario'], 'hola');

    expect($r->tipo)->toBe(R::ERROR)->and($r->texto)->not->toContain('Rate limit')->not->toContain('free-models');
});

it('una llamada HTTP sin simular falla: ninguna prueba sale a la red', function () {
    expect(fn () => Http::get('https://openrouter.ai/api/v1/models'))->toThrow(RuntimeException::class);
});

it('con OpenRouter y modelos gratuitos en producción el asistente falla cerrado y no hay llamadas', function () {
    $e = escenarioAsistente();
    Http::fake();
    usarProveedor('openrouter');
    app()->detectEnvironment(fn () => 'production');

    $r = app(AsistenteService::class)->preguntar($e['alumno_usuario'], 'hola');

    expect($r->tipo)->toBe(R::NO_DISPONIBLE);
    Http::assertNothingSent();
});
