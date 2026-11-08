<?php

use App\Asistencia\AsistenciaIncumplida;
use App\Asistencia\AsistenciaService;
use App\Asistencia\ResumenAsistencia;
use App\Livewire\Asistencia\Tomar\Show;
use App\Models\Asistencia;
use App\Models\EstadoAsistencia;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

function faltaDe($seccion, $alumno): Asistencia
{
    return Asistencia::factory()->con('falta')->create(['seccion_id' => $seccion->id, 'alumno_id' => $alumno->id, 'fecha' => diaHabilReciente()->toDateString()]);
}

it('justifica una falta con motivo y deja de contar como falta', function () {
    ['seccion' => $seccion, 'alumnos' => $alumnos, 'docente' => $docente] = escenarioAsistencia(1);
    $falta = faltaDe($seccion, $alumnos[0]);

    $falta = app(AsistenciaService::class)->justificar($falta, '  Certificado médico ', $docente);

    $hoy = diaHabilReciente();
    $r = app(ResumenAsistencia::class)->delAlumno($alumnos[0], $hoy->subMonth(), $hoy);
    expect($falta->estado->codigo)->toBe('falta_justificada')->and($falta->motivo_justificacion)->toBe('Certificado médico')
        ->and($r['faltas'])->toBe(0)->and($r['registrados'])->toBe(1);
});

it('exige motivo, máximo 255 caracteres y que el registro sea una falta', function () {
    ['seccion' => $seccion, 'alumnos' => $alumnos, 'docente' => $docente] = escenarioAsistencia(1);
    $servicio = app(AsistenciaService::class);
    $falta = faltaDe($seccion, $alumnos[0]);
    $presente = Asistencia::factory()->create(['seccion_id' => $seccion->id, 'alumno_id' => $alumnos[0]->id, 'fecha' => diaHabilReciente(1)->toDateString()]);

    expect(fn () => $servicio->justificar($falta, '   ', $docente))->toThrow(AsistenciaIncumplida::class, 'motivo');
    expect(fn () => $servicio->justificar($falta, str_repeat('x', 256), $docente))->toThrow(AsistenciaIncumplida::class, '255');
    expect(fn () => $servicio->justificar($presente, 'Motivo', $docente))->toThrow(AsistenciaIncumplida::class, 'una falta');
});

it('revertir restaura la falta y limpia el motivo', function () {
    ['seccion' => $seccion, 'alumnos' => $alumnos, 'docente' => $docente] = escenarioAsistencia(1);
    $servicio = app(AsistenciaService::class);
    $justificada = $servicio->justificar(faltaDe($seccion, $alumnos[0]), 'Motivo', $docente);

    $revertida = $servicio->revertir($justificada, $docente);

    expect($revertida->estado->codigo)->toBe('falta')->and($revertida->motivo_justificacion)->toBeNull();
    expect(fn () => $servicio->revertir($revertida, $docente))->toThrow(AsistenciaIncumplida::class, 'no está justificada');
});

it('solo escribe el docente de la sección o el administrador; el resto recibe 403', function () {
    ['seccion' => $seccion, 'alumnos' => $alumnos, 'admin' => $admin] = escenarioAsistencia(1);
    $servicio = app(AsistenciaService::class);
    $falta = faltaDe($seccion, $alumnos[0]);
    $otroDocente = usuarioConPermisos(['asistencia.registrar_propia']);

    foreach ([$otroDocente, usuarioConPermisos(['asistencia.ver_propia']), usuarioConPermisos(['asistencia.ver_todas'])] as $u) {
        expect(fn () => $servicio->justificar($falta, 'Motivo', $u))->toThrow(AccessDeniedHttpException::class);
    }

    expect($servicio->justificar($falta, 'Motivo', $admin)->estado->codigo)->toBe('falta_justificada');
});

it('no se justifica en un año que no es el vigente', function () {
    ['seccion' => $seccion, 'alumnos' => $alumnos, 'docente' => $docente, 'anio' => $anio] = escenarioAsistencia(1);
    $falta = faltaDe($seccion, $alumnos[0]);
    $anio->forceFill(['vigente' => null])->save();

    expect(fn () => app(AsistenciaService::class)->justificar($falta->fresh(), 'Motivo', $docente))->toThrow(AsistenciaIncumplida::class, 'vigente');
});

it('guardar la lista de nuevo conserva el motivo si el estado no cambió y lo limpia si cambia', function () {
    ['seccion' => $seccion, 'alumnos' => $alumnos, 'docente' => $docente] = escenarioAsistencia(1);
    $servicio = app(AsistenciaService::class);
    $justificada = $servicio->justificar(faltaDe($seccion, $alumnos[0]), 'Motivo', $docente);
    $dia = diaHabilReciente();

    $servicio->guardar($seccion, $dia, [$alumnos[0]->id => ['estado_id' => $justificada->estado_id]], $docente);
    expect($justificada->fresh()->motivo_justificacion)->toBe('Motivo');

    $servicio->guardar($seccion, $dia, [$alumnos[0]->id => ['estado_id' => EstadoAsistencia::where('codigo', 'presente')->value('id')]], $docente);
    expect($justificada->fresh()->motivo_justificacion)->toBeNull();
});

it('la pantalla ofrece justificar y revertir solo con permiso de escritura', function () {
    ['seccion' => $seccion, 'alumnos' => $alumnos, 'docente' => $docente] = escenarioAsistencia(1);
    $falta = faltaDe($seccion, $alumnos[0]);
    $dia = $falta->fecha->toDateString();

    $c = Livewire::actingAs($docente)->test(Show::class, ['seccion' => $seccion, 'fecha' => $dia])->assertSee('Justificar');
    $c->call('iniciarJustificacion', $falta->id)->set('motivo', 'Cita médica')->call('justificar')->assertHasNoErrors();
    expect($falta->fresh()->estado->codigo)->toBe('falta_justificada');
    $c->assertSee('Revertir')->call('revertir', $falta->id);
    expect($falta->fresh()->estado->codigo)->toBe('falta');
});
