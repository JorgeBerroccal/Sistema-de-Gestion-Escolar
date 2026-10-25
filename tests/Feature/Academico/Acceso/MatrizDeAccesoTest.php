<?php

use App\Models\AnioEscolar;
use App\Models\AsignacionDocente;
use App\Models\Curso;

dataset('rolesPorDefecto', ['Administrador', 'Director', 'Docente', 'Padre de Familia', 'Alumno']);

/** uri => roles con acceso (200); el resto recibe 403. */
function matrizAcceso(): array
{
    $consulta = ['Administrador', 'Director'];

    return [
        '/academico/institucion' => $consulta,
        '/academico/niveles' => $consulta,
        '/academico/anios' => $consulta,
        '/academico/anios/crear' => ['Administrador'],
        '/academico/anios/{anio}/editar' => ['Administrador'],
        '/academico/grados' => $consulta,
        '/academico/secciones' => $consulta,
        '/academico/cursos' => $consulta,
        '/academico/cursos/crear' => ['Administrador'],
        '/academico/cursos/{curso}/editar' => ['Administrador'],
        '/academico/asignaciones' => $consulta,
        '/academico/asignaciones/crear' => ['Administrador'],
        '/academico/mis-asignaciones' => ['Docente'],
    ];
}

it('cada ruta del módulo responde 200 o 403 según el rol por defecto', function (string $rol) {
    $usuario = usuarioConRolDeSeeder($rol);
    $anio = AnioEscolar::factory()->create();
    $curso = Curso::factory()->create();

    foreach (matrizAcceso() as $uri => $conAcceso) {
        $url = strtr($uri, ['{anio}' => $anio->id, '{curso}' => $curso->id]);
        $esperado = in_array($rol, $conAcceso, true) ? 200 : 403;

        expect($this->actingAs($usuario)->get($url)->status())->toBe($esperado, "{$rol} en {$uri}");
    }
})->with('rolesPorDefecto');

it('la asignación individual la ve quien tiene ver_todas, y el docente solo la suya', function () {
    $docente = usuarioConRolDeSeeder('Docente');
    $propia = AsignacionDocente::factory()->create(['usuario_id' => $docente->id]);
    $ajena = AsignacionDocente::factory()->create();

    foreach (['Administrador', 'Director'] as $rol) {
        $this->actingAs(usuarioConRolDeSeeder($rol))->get("/academico/asignaciones/{$ajena->id}")->assertOk();
    }
    $this->actingAs($docente)->get("/academico/asignaciones/{$propia->id}")->assertOk();
    $this->actingAs($docente)->get("/academico/asignaciones/{$ajena->id}")->assertForbidden();

    foreach (['Padre de Familia', 'Alumno'] as $rol) {
        $this->actingAs(usuarioConRolDeSeeder($rol))->get("/academico/asignaciones/{$propia->id}")->assertForbidden();
    }
});

it('la navegación solo ofrece lo permitido a cada rol', function (string $rol, array $visibles, array $ocultos) {
    $respuesta = $this->actingAs(usuarioConRolDeSeeder($rol))->get('/inicio');

    foreach ($visibles as $etiqueta) {
        $respuesta->assertSee($etiqueta);
    }
    foreach ($ocultos as $etiqueta) {
        $respuesta->assertDontSee($etiqueta);
    }
})->with([
    'Administrador' => ['Administrador', ['Institución', 'Años escolares', 'Grados', 'Secciones', 'Cursos', 'Asignaciones'], ['Mis asignaciones']],
    'Director' => ['Director', ['Institución', 'Años escolares', 'Grados', 'Secciones', 'Cursos', 'Asignaciones'], ['Mis asignaciones']],
    'Docente' => ['Docente', ['Mis asignaciones'], ['Institución', 'Años escolares', 'Grados', 'Secciones', 'Cursos']],
    'Padre de Familia' => ['Padre de Familia', [], ['Institución', 'Años escolares', 'Grados', 'Secciones', 'Cursos', 'Asignaciones']],
    'Alumno' => ['Alumno', [], ['Institución', 'Años escolares', 'Grados', 'Secciones', 'Cursos', 'Asignaciones']],
]);
