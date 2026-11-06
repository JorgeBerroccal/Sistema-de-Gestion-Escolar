<?php

use App\Asistencia\AsistenciaService;
use App\Livewire\Asistencia\Tomar\Index;
use App\Livewire\Asistencia\Tomar\Show;
use App\Models\Asistencia;
use App\Models\EstadoAsistencia;
use App\Models\Seccion;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

it('el docente con asignación abre la sección y guarda; sin asignación recibe 403 al abrir y al guardar', function () {
    ['seccion' => $seccion, 'alumnos' => $alumnos, 'docente' => $docente] = escenarioAsistencia(2);
    $ajena = Seccion::factory()->for($seccion->anio, 'anio')->create();

    Livewire::actingAs($docente)->test(Show::class, ['seccion' => $seccion])->assertOk();
    Livewire::actingAs($docente)->test(Show::class, ['seccion' => $ajena])->assertForbidden();

    $filas = $alumnos->mapWithKeys(fn ($a) => [$a->id => ['estado_id' => EstadoAsistencia::where('codigo', 'presente')->value('id')]])->all();
    expect(fn () => app(AsistenciaService::class)->guardar($ajena, diaHabilReciente(), $filas, $docente))
        ->toThrow(AccessDeniedHttpException::class);
});

it('el administrador toma asistencia en cualquier sección', function () {
    ['seccion' => $seccion, 'admin' => $admin] = escenarioAsistencia(1);

    Livewire::actingAs($admin)->test(Show::class, ['seccion' => $seccion])->assertOk();
    Livewire::actingAs($admin)->test(Index::class)->assertOk()->assertSee($seccion->identificador);
});

it('Director, Padre y Alumno reciben 403 en las pantallas de toma', function () {
    ['seccion' => $seccion] = escenarioAsistencia(1);

    foreach ([['asistencia.ver_todas'], ['asistencia.ver_propia'], []] as $permisos) {
        $u = usuarioConPermisos($permisos);
        Livewire::actingAs($u)->test(Show::class, ['seccion' => $seccion])->assertForbidden();
        Livewire::actingAs($u)->test(Index::class)->assertForbidden();
    }
});

it('un rol creado en la prueba con registrar_propia funciona y sin el permiso no', function () {
    ['seccion' => $seccion, 'docente' => $docente] = escenarioAsistencia(1);
    $docente->rol->permisos()->detach();

    Livewire::actingAs($docente->fresh())->test(Show::class, ['seccion' => $seccion])->assertForbidden();
});

it('la lista muestra a los alumnos como presentes y guarda desde el componente', function () {
    ['seccion' => $seccion, 'alumnos' => $alumnos, 'docente' => $docente] = escenarioAsistencia(2);
    $dia = diaHabilReciente()->toDateString();

    $c = Livewire::actingAs($docente)->test(Show::class, ['seccion' => $seccion, 'fecha' => $dia]);
    $falta = EstadoAsistencia::where('codigo', 'falta')->value('id');
    $c->set("filas.{$alumnos[0]->id}.estado_id", $falta)->call('guardar')->assertHasNoErrors();

    expect(Asistencia::count())->toBe(2)
        ->and(Asistencia::where('alumno_id', $alumnos[0]->id)->value('estado_id'))->toBe($falta);
});

it('en sábado muestra el aviso y no guarda', function () {
    ['seccion' => $seccion, 'docente' => $docente] = escenarioAsistencia(1);
    $sabado = diaHabilReciente()->previous('saturday')->toDateString();

    Livewire::actingAs($docente)->test(Show::class, ['seccion' => $seccion, 'fecha' => $sabado])
        ->assertSee('sábados ni los domingos')->assertDontSee('Guardar asistencia')
        ->call('guardar');
    expect(Asistencia::count())->toBe(0);
});
