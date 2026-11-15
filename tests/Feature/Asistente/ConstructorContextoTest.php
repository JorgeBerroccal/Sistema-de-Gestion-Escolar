<?php

use App\Asistente\ConstructorContexto;
use App\Models\Asistencia;

function contextoDe(array $e, $usuario, $alumno): string
{
    return app(ConstructorContexto::class)->construir($usuario, $alumno->fresh());
}

it('incluye matrícula y sección, notas publicadas con coma decimal y asistencia', function () {
    $e = escenarioAsistente();

    $texto = contextoDe($e, $e['alumno_usuario'], $e['ana']);

    expect($texto)->toContain('Estudiante: Ana Alfa')
        ->and($texto)->toContain('Matrícula')->toContain($e['seccion']->fresh()->load('grado.nivel')->etiqueta)->toContain('Matriculado')
        ->and($texto)->toContain('Periodo 1')->toContain('Promedio general: 13,00')
        ->and($texto)->toContain($e['curso']->nombre)->toContain('Práctica (60')->toContain('Examen (40')
        ->and($texto)->toContain('Asistencia del año')->toContain('faltas: 1')->toContain('Asistencia del mes en curso');
});

it('sin periodos publicados lo dice y no incluye ninguna nota', function () {
    $e = escenarioAsistente(cerrar: false);

    $texto = contextoDe($e, $e['alumno_usuario'], $e['ana']);

    expect($texto)->toContain('ningún periodo publicado')->not->toContain('Práctica');
});

it('un alumno sin matrícula ni registros lo indica sin inventar', function () {
    $e = escenarioAsistente();
    $e['ana']->matriculas()->delete();
    Asistencia::query()->where('alumno_id', $e['ana']->id)->delete();

    $texto = contextoDe($e, $e['alumno_usuario'], $e['ana']);

    expect($texto)->toContain('no tiene matrícula registrada')->toContain('sin registros de asistencia');
});

it('omite cada bloque cuando al usuario le falta el permiso de su pantalla', function (string $permiso, string $presente, string $ausente) {
    $e = escenarioAsistente();
    $usuario = usuarioConPermisos(array_values(array_diff($e['permisos'], [$permiso])));
    $e['ana']->apoderados()->attach($usuario->id, ['parentesco' => 'tutor']);

    $texto = contextoDe($e, $usuario, $e['ana']);

    expect($texto)->toContain($presente)->not->toContain($ausente);
})->with([
    'notas' => ['notas.ver_propia', 'Notas: el usuario no tiene acceso', 'Promedio general'],
    'asistencia' => ['asistencia.ver_propia', 'Asistencia: el usuario no tiene acceso', 'faltas: 1'],
    'matrícula' => ['matricula.ver_propia', 'Matrícula y sección: el usuario no tiene acceso', 'Matriculado'],
]);

it('no contiene documento, fecha de nacimiento ni correos', function () {
    $e = escenarioAsistente();

    $texto = contextoDe($e, $e['padre'], $e['ana']);

    expect($texto)->not->toContain('47851236')->not->toContain('2011')->not->toContain('17/03')
        ->not->toContain('reconocible@ejemplo.test')->not->toContain('@');
});
