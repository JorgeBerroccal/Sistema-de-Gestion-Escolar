<?php

use App\Asistente\ResolverAlumno;

it('con un solo alumno propio lo elige sin importar el mensaje', function () {
    $e = escenarioAsistente();

    $r = app(ResolverAlumno::class)->resolver($e['alumno_usuario'], 'hola');

    expect($r->alumno?->id)->toBe($e['ana']->id)->and($r->aclaracion)->toBeNull();
});

it('con varios hijos elige al nombrado, sin tildes ni mayúsculas', function () {
    $e = escenarioAsistente();
    $e['ana']->update(['nombres' => 'María José']);

    expect(app(ResolverAlumno::class)->resolver($e['padre'], '¿cuántas faltas tiene BETO?')->alumno?->id)->toBe($e['beto']->id)
        ->and(app(ResolverAlumno::class)->resolver($e['padre'], 'notas de maria jose')->alumno?->id)->toBe($e['ana']->id);
});

it('con varios hijos y sin nombre pide aclarar, listando solo a sus hijos', function () {
    $e = escenarioAsistente();

    $r = app(ResolverAlumno::class)->resolver($e['padre'], '¿cómo va en Matemática?');

    expect($r->alumno)->toBeNull()->and($r->aclaracion)->toContain('Ana')->toContain('Beto');
});

it('con nombres parecidos entre hermanos no adivina', function () {
    $e = escenarioAsistente();
    $e['ana']->update(['nombres' => 'Luis']);
    $e['beto']->update(['nombres' => 'Luis Miguel']);

    expect(app(ResolverAlumno::class)->resolver($e['padre'], 'notas de Luis')->alumno?->id)->toBe($e['ana']->id)
        ->and(app(ResolverAlumno::class)->resolver($e['padre'], 'notas de Luis Miguel')->alumno?->id)->toBe($e['beto']->id);

    $e['ana']->update(['nombres' => 'Luis Carlos']);

    expect(app(ResolverAlumno::class)->resolver($e['padre'], 'notas de Luis')->aclaracion)->not->toBeNull();
});

it('un nombre ajeno no coincide y no revela nada: con varios hijos pide aclarar', function () {
    $e = escenarioAsistente();

    $r = app(ResolverAlumno::class)->resolver($e['padre'], 'dame las notas de Zacarías Pérez');

    expect($r->alumno)->toBeNull()->and($r->aclaracion)->not->toContain('Zacar');
});

it('sin alumnos vinculados responde que no hay datos', function () {
    escenarioAsistente();
    $solo = usuarioConPermisos(['asistente.usar']);

    expect(app(ResolverAlumno::class)->resolver($solo, 'hola')->sinDatos)->toContain('No tienes estudiantes');
});

it('un usuario con permisos de ver todo sigue limitado a lo propio', function () {
    escenarioAsistente();
    $director = usuarioConPermisos(['asistente.usar', 'notas.ver_todas', 'matricula.ver_todas', 'asistencia.ver_todas']);

    expect(app(ResolverAlumno::class)->resolver($director, 'notas de Ana')->sinDatos)->not->toBeNull();
});
