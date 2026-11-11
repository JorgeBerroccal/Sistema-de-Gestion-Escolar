<?php

use App\Evaluacion\BoletaService;
use App\Models\Alumno;
use App\Models\CierrePeriodo;
use App\Models\CriterioEvaluacion;
use App\Models\Curso;
use App\Models\Nota;
use App\Models\Permiso;

function descargarBoleta($usuario, Alumno|int $alumno, int|string $periodo)
{
    return test()->actingAs($usuario)->get(route('boletas.descargar', [$alumno instanceof Alumno ? $alumno->id : $alumno, $periodo]));
}

function esPdf($respuesta): void
{
    $respuesta->assertOk()->assertHeader('content-type', 'application/pdf');
    expect($respuesta->getContent())->toStartWith('%PDF')
        ->and($respuesta->headers->get('content-disposition'))->toContain('attachment');
}

it('el Administrador y el Director descargan la boleta de cualquier alumno con nombre de archivo sin datos personales', function () {
    $b = escenarioBoleta();

    foreach ([$b['admin'], usuarioConPermisos(['boletas.ver_todas'])] as $usuario) {
        $r = descargarBoleta($usuario, $b['ana'], $b['periodo']->id);
        esPdf($r);
        expect($r->headers->get('content-disposition'))->toContain("boleta-{$b['ana']->id}-{$b['periodo']->id}.pdf")
            ->not->toContain($b['ana']->apellidos);
    }

    esPdf(descargarBoleta($b['admin'], $b['ana'], 'anual'));
});

it('un periodo sin cerrar sale como borrador para Administrador y Director, no para la familia', function () {
    $b = escenarioBoleta(cerrar: false);
    $padre = usuarioConPermisos(['boletas.ver_propia']);
    $b['ana']->apoderados()->attach($padre->id, ['parentesco' => 'padre']);

    esPdf(descargarBoleta($b['admin'], $b['ana'], $b['periodo']->id));
    esPdf(descargarBoleta(usuarioConPermisos(['boletas.ver_todas']), $b['ana'], $b['periodo']->id));
    descargarBoleta($padre, $b['ana'], $b['periodo']->id)->assertForbidden();
    descargarBoleta($padre, $b['ana'], 'anual')->assertForbidden();

    CierrePeriodo::factory()->create(['seccion_id' => $b['seccion']->id, 'periodo_id' => $b['periodo']->id]);

    esPdf(descargarBoleta($padre, $b['ana'], $b['periodo']->id));
    esPdf(descargarBoleta($padre, $b['ana'], 'anual'));
});

it('la familia solo descarga la boleta del propio alumno o de un hijo', function () {
    $b = escenarioBoleta();
    $padre = usuarioConPermisos(['boletas.ver_propia']);
    $b['ana']->apoderados()->attach($padre->id, ['parentesco' => 'madre']);
    $alumnoConUsuario = usuarioConPermisos(['boletas.ver_propia']);
    $b['beto']->update(['usuario_id' => $alumnoConUsuario->id]);

    esPdf(descargarBoleta($padre, $b['ana'], $b['periodo']->id));
    descargarBoleta($padre, $b['beto'], $b['periodo']->id)->assertForbidden();
    esPdf(descargarBoleta($alumnoConUsuario, $b['beto'], $b['periodo']->id));
    descargarBoleta($alumnoConUsuario, $b['ana'], $b['periodo']->id)->assertForbidden();
});

it('un alumno ajeno es 403 aunque no exista, y un usuario sin vínculos no descarga nada', function () {
    $b = escenarioBoleta();
    $padre = usuarioConPermisos(['boletas.ver_propia']);

    descargarBoleta($padre, $b['ana'], $b['periodo']->id)->assertForbidden();
    descargarBoleta($padre, 999999, $b['periodo']->id)->assertForbidden();
});

it('el Docente recibe 403 en la boleta aunque tenga asignación en la sección (mínimo privilegio)', function () {
    $b = escenarioBoleta();

    descargarBoleta($b['docente'], $b['ana'], $b['periodo']->id)->assertForbidden();
    descargarBoleta($b['docente'], $b['ana'], 'anual')->assertForbidden();
});

it('un rol con solo boletas.ver_propia (sin notas.ver_propia) descarga la boleta completa de su hijo y no la de uno ajeno', function () {
    $b = escenarioBoleta();
    // Otro curso con notas: la boleta debe incluirlo aunque el rol no tenga permisos de notas.
    $otro = Curso::factory()->create(['nivel_id' => $b['curso']->nivel_id]);
    $b['seccion']->grado->cursos()->attach([$b['curso']->id, $otro->id]);
    CriterioEvaluacion::factory()->create(['seccion_id' => $b['seccion']->id, 'curso_id' => $otro->id, 'periodo_id' => $b['periodo']->id, 'peso' => 100]);
    $soloBoleta = usuarioConPermisos(['boletas.ver_propia']);
    $b['ana']->apoderados()->attach($soloBoleta->id, ['parentesco' => 'padre']);

    esPdf(descargarBoleta($soloBoleta, $b['ana'], $b['periodo']->id));
    descargarBoleta($soloBoleta, $b['beto'], $b['periodo']->id)->assertForbidden();

    $datos = app(BoletaService::class)->datos($b['ana'], $b['periodo'], soloPublicados: true);
    expect(collect($datos['cursos'])->pluck('curso.id')->all())->toContain($b['curso']->id)->toContain($otro->id);
});

it('Padre y Alumno sin permiso, y un rol sin permisos de boleta, reciben 403; sin sesión va al login', function () {
    $b = escenarioBoleta();
    $padre = usuarioConPermisos(['notas.ver_propia']); // tiene notas pero no boletas
    $b['ana']->apoderados()->attach($padre->id, ['parentesco' => 'padre']);

    descargarBoleta($padre, $b['ana'], $b['periodo']->id)->assertForbidden();
    descargarBoleta(usuarioConPermisos([]), $b['ana'], $b['periodo']->id)->assertForbidden();
    descargarBoleta(usuarioConPermisos(['notas.ver_todas', 'notas.gestionar']), $b['ana'], $b['periodo']->id)->assertForbidden();

    auth()->logout();
    test()->get(route('boletas.descargar', [$b['ana']->id, $b['periodo']->id]))->assertRedirect(route('login'));
});

it('periodo o alumno inexistente es 404 para quien puede ver todas; un periodo mal formado no enruta', function () {
    $b = escenarioBoleta();

    descargarBoleta($b['admin'], $b['ana'], 999999)->assertNotFound();
    descargarBoleta($b['admin'], 999999, $b['periodo']->id)->assertNotFound();
    test()->actingAs($b['admin'])->get('/boletas/'.$b['ana']->id.'/abc')->assertNotFound();
});

it('un rol creado en la prueba con y sin el permiso de boleta', function () {
    $b = escenarioBoleta();
    $usuario = usuarioConPermisos(['boletas.ver_todas']);

    esPdf(descargarBoleta($usuario, $b['ana'], $b['periodo']->id));

    $usuario->rol->permisos()->detach(Permiso::where('identificador', 'boletas.ver_todas')->value('id'));

    descargarBoleta($usuario->fresh(), $b['ana'], $b['periodo']->id)->assertForbidden();
});

it('quitar boletas.ver_todas al Director corta su acceso aunque conserve las notas', function () {
    $director = usuarioConRolDeSeeder('Director'); // antes del escenario: el seeder asigna al crear cada permiso
    $b = escenarioBoleta();

    esPdf(descargarBoleta($director, $b['ana'], $b['periodo']->id));

    $director->rol->permisos()->detach(Permiso::where('identificador', 'boletas.ver_todas')->value('id'));

    descargarBoleta($director->fresh(), $b['ana'], $b['periodo']->id)->assertForbidden();
});

it('SC-006: una boleta de 12 cursos × 4 criterios se genera y descarga en menos de 5 segundos', function () {
    $b = escenarioBoleta();
    $cursos = Curso::factory()->count(11)->create(['nivel_id' => $b['curso']->nivel_id]);
    $b['seccion']->grado->cursos()->attach($cursos->pluck('id'));
    $b['seccion']->grado->cursos()->attach($b['curso']->id);
    foreach ($cursos as $curso) {
        $criterios = collect(range(1, 4))->map(fn ($i) => CriterioEvaluacion::factory()->create([
            'seccion_id' => $b['seccion']->id, 'curso_id' => $curso->id, 'periodo_id' => $b['periodo']->id, 'nombre' => "C{$i}", 'peso' => 25, 'orden' => $i,
        ]));
        foreach ($criterios as $c) {
            Nota::factory()->create(['criterio_id' => $c->id, 'alumno_id' => $b['ana']->id, 'valor' => '14', 'valor_numerico' => 14]);
        }
    }
    $padre = usuarioConPermisos(['boletas.ver_propia']);
    $b['ana']->apoderados()->attach($padre->id, ['parentesco' => 'padre']);

    $inicio = microtime(true);
    $r = descargarBoleta($padre, $b['ana'], $b['periodo']->id);
    $segundos = microtime(true) - $inicio;

    esPdf($r);
    expect($segundos)->toBeLessThan(5.0);
});

it('los enlaces a la boleta aparecen donde corresponde: «Mis notas» (3 clics) y la tabla de notas del Administrador', function () {
    $b = escenarioBoleta();
    $padre = usuarioConPermisos(['notas.ver_propia', 'boletas.ver_propia']);
    $b['ana']->apoderados()->attach($padre->id, ['parentesco' => 'padre']);
    $sinBoleta = usuarioConPermisos(['notas.ver_propia']);
    $b['ana']->apoderados()->attach($sinBoleta->id, ['parentesco' => 'madre']);

    // Clic 1: inicio → «Mis notas»; clic 2: el hijo ya está elegido; clic 3: «Descargar boleta».
    test()->actingAs($padre)->get('/inicio')->assertSee(route('notas.propia.index'), false);
    test()->actingAs($padre)->get(route('notas.propia.index'))->assertOk()
        ->assertSee(route('boletas.descargar', [$b['ana']->id, $b['periodo']->id]), false)->assertSee('Descargar boleta anual');
    test()->actingAs($sinBoleta)->get(route('notas.propia.index'))->assertOk()->assertDontSee('Descargar boleta');

    test()->actingAs($b['admin'])->get(route('notas.registro.show', ['seccion' => $b['seccion'], 'curso' => $b['curso'], 'periodo' => $b['periodo']->id]))
        ->assertOk()->assertSee(route('boletas.descargar', [$b['ana']->id, $b['periodo']->id]), false);
    test()->actingAs($b['docente'])->get(route('notas.registro.show', ['seccion' => $b['seccion'], 'curso' => $b['curso'], 'periodo' => $b['periodo']->id]))
        ->assertOk()->assertDontSee('/boletas/');
});
