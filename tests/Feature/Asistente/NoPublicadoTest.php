<?php

use App\Asistente\AsistenteService;
use App\Models\AnioEscolar;
use App\Models\CierrePeriodo;

/**
 * Los valores de las notas del escenario salen en el contexto como `Criterio (peso %): valor`. Se buscan con ese formato y no
 * como un número suelto: el nombre del año escolar lo genera la fábrica al azar (2000–2999) y un «15» puede aparecer en él.
 */
const NOTA_EN_CONTEXTO = '/\):\s*(15|10)\b/';

function textoParaProveedor(array $e, $usuario, string $mensaje = '¿Cuáles son mis notas?'): string
{
    app(AsistenteService::class)->preguntar($usuario, $mensaje);

    return $e['falso']->textoRecibido();
}

it('una nota de un periodo no cerrado no llega al proveedor', function () {
    $e = escenarioAsistente(cerrar: false);

    $texto = textoParaProveedor($e, $e['alumno_usuario']);

    expect($texto)->toContain('ningún periodo publicado')->not->toContain('Práctica')->not->toContain('Examen')
        ->not->toContain('13,00')->not->toMatch(NOTA_EN_CONTEXTO);
});

it('la comprobación de notas no publicadas no depende del nombre del año escolar', function (string $anio) {
    $e = escenarioAsistente(cerrar: false);
    AnioEscolar::query()->update(['nombre' => $anio]);

    $texto = textoParaProveedor($e, $e['alumno_usuario']);

    expect($texto)->toContain("Matrícula {$anio}")->toContain('ningún periodo publicado')
        ->not->toContain('Práctica')->not->toContain('Examen')->not->toContain('13,00')->not->toMatch(NOTA_EN_CONTEXTO);
})->with(['2015', '2150', '2115', '2010']);

it('un periodo reabierto después de publicado deja de estar disponible para el asistente', function () {
    $e = escenarioAsistente();
    expect(textoParaProveedor($e, $e['alumno_usuario']))->toContain('Promedio general: 13,00');

    CierrePeriodo::query()->delete();
    $e['falso']->recibidos = [];

    expect(textoParaProveedor($e, $e['alumno_usuario']))->not->toContain('13,00')->not->toContain('Práctica');
});

it('las notas del periodo publicado sí llegan', function () {
    $e = escenarioAsistente();

    expect(textoParaProveedor($e, $e['padre'], 'notas de Ana'))->toContain('Periodo 1')->toContain('13,00')->toContain('Práctica')
        ->toMatch(NOTA_EN_CONTEXTO);
});

it('un usuario con permisos de ver todo y asistente.usar solo recibe lo suyo', function () {
    $e = escenarioAsistente();
    $usuario = usuarioConPermisos([...$e['permisos'], 'notas.ver_todas', 'matricula.ver_todas', 'asistencia.ver_todas']);
    $e['beto']->apoderados()->attach($usuario->id, ['parentesco' => 'tutor']);

    $texto = textoParaProveedor($e, $usuario, 'dame las notas de Ana');

    expect($texto)->toContain('Beto Beta')->not->toContain('Ana Alfa')->not->toContain('13,00');
});

it('sin el permiso de notas el bloque de notas no llega, aunque haya notas publicadas', function () {
    $e = escenarioAsistente();
    $usuario = usuarioConPermisos(array_values(array_diff($e['permisos'], ['notas.ver_propia'])));
    $e['ana']->apoderados()->attach($usuario->id, ['parentesco' => 'tutor']);

    expect(textoParaProveedor($e, $usuario))->toContain('Notas: el usuario no tiene acceso')->not->toContain('13,00')->not->toContain('Práctica');
});

it('sin los permisos de asistencia y matrícula esos bloques no llegan', function () {
    $e = escenarioAsistente();
    $usuario = usuarioConPermisos(['asistente.usar', 'notas.ver_propia']);
    $e['ana']->apoderados()->attach($usuario->id, ['parentesco' => 'tutor']);

    expect(textoParaProveedor($e, $usuario))->toContain('Asistencia: el usuario no tiene acceso')->toContain('Matrícula y sección: el usuario no tiene acceso')
        ->not->toContain('faltas: 1')->not->toContain('Matriculado');
});
