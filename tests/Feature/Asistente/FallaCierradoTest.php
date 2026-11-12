<?php

use App\Asistente\AsistenteService;
use App\Asistente\Proveedores\ProveedorFalso;
use App\Asistente\Proveedores\ProveedorIA;
use App\Asistente\ResultadoAsistente as R;
use App\Livewire\Asistente\Chat\Index;
use Livewire\Livewire;

function enProduccionSinDatosReales(array $e): void
{
    app()->detectEnvironment(fn () => 'production');
    $e['falso']->declararDatosReales(false);
}

it('en producción con un adaptador sin datos reales el asistente se niega y no llama al proveedor', function () {
    $e = escenarioAsistente();
    enProduccionSinDatosReales($e);

    $r = app(AsistenteService::class)->preguntar($e['alumno_usuario'], '¿cómo voy?');

    expect($r->tipo)->toBe(R::NO_DISPONIBLE)->and($r->texto)->toContain('deshabilitado')->and($e['falso']->llamadas())->toBe(0);
});

it('la pantalla muestra el mensaje claro y no ofrece el formulario', function () {
    $e = escenarioAsistente();
    enProduccionSinDatosReales($e);

    Livewire::actingAs($e['alumno_usuario'])->test(Index::class)
        ->assertSee('deshabilitado en este entorno')->assertDontSee('Escribe tu pregunta')->assertSee('Las respuestas las genera una IA');
});

it('un envío con la pantalla ya abierta también falla cerrado si cambia la configuración', function () {
    $e = escenarioAsistente();
    $c = Livewire::actingAs($e['alumno_usuario'])->test(Index::class)->assertSee('Escribe tu pregunta');

    enProduccionSinDatosReales($e);
    $c->set('mensaje', 'hola')->call('enviar')->assertSee('deshabilitado en este entorno');

    expect($e['falso']->llamadas())->toBe(0);
});

it('la negativa ocurre antes del límite: no consume cupo', function () {
    $e = escenarioAsistente();
    config(['asistente.limites' => ['usuario_minuto' => 1, 'usuario_dia' => 10, 'global_minuto' => 10, 'global_dia' => 10]]);
    $servicio = app(AsistenteService::class);

    enProduccionSinDatosReales($e);
    $servicio->preguntar($e['alumno_usuario'], 'uno');
    $servicio->preguntar($e['alumno_usuario'], 'dos');

    app()->detectEnvironment(fn () => 'testing');

    expect($servicio->preguntar($e['alumno_usuario'], 'tres')->tipo)->toBe(R::RESPUESTA);
});

it('en producción el adaptador falso nunca admite datos reales aunque se declare que sí', function () {
    $e = escenarioAsistente();
    app()->detectEnvironment(fn () => 'production');
    $e['falso']->declararDatosReales(true);

    $r = app(AsistenteService::class)->preguntar($e['alumno_usuario'], 'hola');

    expect($r->tipo)->toBe(R::NO_DISPONIBLE)->and($e['falso']->llamadas())->toBe(0);
});

it('en producción un adaptador que sí admite datos reales funciona', function () {
    $e = escenarioAsistente();
    app()->detectEnvironment(fn () => 'production');
    app()->instance(ProveedorIA::class, new class extends ProveedorFalso
    {
        public function admiteDatosReales(): bool
        {
            return true;
        }
    });

    expect(app(AsistenteService::class)->preguntar($e['alumno_usuario'], 'hola')->tipo)->toBe(R::RESPUESTA);
});
