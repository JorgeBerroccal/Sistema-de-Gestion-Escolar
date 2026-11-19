<?php

use App\Academico\AsignacionService;
use App\Models\Rol;

/** R-06: el Director supervisa, no administra accesos. */
it('el Director sembrado solo tiene permisos de consulta académica, sin usuarios ni roles', function () {
    usuarioConRolDeSeeder('Director');

    expect(Rol::where('nombre', 'Director')->first()->permisos()->pluck('identificador')->sort()->values()->all())
        ->toBe(['academico.ver', 'asignaciones.ver_todas', 'asistencia.ver_todas', 'boletas.ver_todas', 'matricula.ver_todas', 'notas.ver_todas']);
});

it('el Director no abre Usuarios, Roles ni Permisos', function () {
    $director = usuarioConRolDeSeeder('Director');

    foreach (['admin.usuarios.index', 'admin.roles.index', 'admin.permisos.index'] as $ruta) {
        $this->actingAs($director)->get(route($ruta))->assertForbidden();
    }
});

it('sin usuarios.ver ni roles.ver el Director abre todas sus pantallas y ve los nombres de los docentes', function () {
    $director = usuarioConRolDeSeeder('Director');
    ['seccion' => $seccion, 'cursos' => $cursos, 'docente' => $docente] = escenarioAsignacion(1);
    app(AsignacionService::class)->asignar($seccion, $cursos[0], $docente);

    $this->actingAs($director)->get(route('academico.asignaciones.index'))->assertOk()->assertSee($docente->nombre_completo);

    foreach (['academico.institucion', 'academico.niveles.index', 'academico.anios.index', 'academico.grados.index', 'academico.secciones.index', 'academico.cursos.index',
        'matricula.alumnos.index', 'matricula.nominas.index', 'asistencia.reportes.index', 'notas.reportes.index'] as $ruta) {
        $this->actingAs($director)->get(route($ruta))->assertOk();
    }
});
