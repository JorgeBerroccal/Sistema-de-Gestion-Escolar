<?php

use App\Livewire\Academico\Asignaciones\Index;
use App\Models\AnioEscolar;
use App\Models\AsignacionDocente;
use App\Models\Permiso;
use App\Models\Seccion;
use Livewire\Livewire;

it('el Docente ve solo sus asignaciones, de todos los años', function () {
    $docente = usuarioConRolDeSeeder('Docente');
    $otro = usuarioConRolDeSeeder('Docente');
    $anioViejo = AnioEscolar::factory()->create();
    $vigente = AnioEscolar::factory()->vigente()->create();

    $m1 = AsignacionDocente::factory()->create(['usuario_id' => $docente->id, 'seccion_id' => Seccion::factory()->create(['anio_escolar_id' => $vigente->id])->id]);
    $m2 = AsignacionDocente::factory()->create(['usuario_id' => $docente->id, 'seccion_id' => Seccion::factory()->create(['anio_escolar_id' => $anioViejo->id])->id]);
    AsignacionDocente::factory()->create(['usuario_id' => $otro->id]);

    $c = Livewire::actingAs($docente)->test(Index::class);

    expect($c->viewData('asignaciones')->pluck('id')->sort()->values()->all())->toBe([$m1->id, $m2->id]);
    $this->actingAs($docente)->get('/academico/mis-asignaciones')->assertOk();
});

it('no ve las de otro docente: abrir una asignación ajena da 403 sin datos', function () {
    $docente = usuarioConRolDeSeeder('Docente');
    $ajena = AsignacionDocente::factory()->create();
    $ajena->load('curso');

    $this->actingAs($docente)->get("/academico/asignaciones/{$ajena->id}")->assertForbidden()->assertDontSee($ajena->curso->nombre);
});

it('sin regla registrada en config/pertenencia.php todo da 403 (fail-closed)', function () {
    $docente = usuarioConRolDeSeeder('Docente');
    $propia = AsignacionDocente::factory()->create(['usuario_id' => $docente->id]);
    config(['pertenencia.reglas' => []]);

    $this->actingAs($docente)->get("/academico/asignaciones/{$propia->id}")->assertForbidden();
    $this->actingAs($docente)->get('/academico/mis-asignaciones')->assertForbidden();
});

it('quitar asignaciones.ver_propias a su rol lo deja sin acceso sin tocar código', function () {
    $docente = usuarioConRolDeSeeder('Docente');
    $propia = AsignacionDocente::factory()->create(['usuario_id' => $docente->id]);
    $this->actingAs($docente)->get('/academico/mis-asignaciones')->assertOk();

    $docente->rol->permisos()->detach(Permiso::where('identificador', 'asignaciones.ver_propias')->first());
    $docente = $docente->fresh();

    $this->actingAs($docente)->get('/academico/mis-asignaciones')->assertForbidden();
    $this->actingAs($docente)->get("/academico/asignaciones/{$propia->id}")->assertForbidden();
});

it('el Administrador y el Director ven todas las asignaciones', function () {
    AsignacionDocente::factory()->count(3)->create();

    foreach (['Administrador', 'Director'] as $rol) {
        $c = Livewire::actingAs(usuarioConRolDeSeeder($rol))->test(Index::class);
        expect($c->viewData('asignaciones')->total())->toBe(3);
    }
});

it('un usuario sin ninguno de los dos permisos de asignaciones no entra al listado', function () {
    Livewire::actingAs(usuarioConPermisos(['academico.ver']))->test(Index::class)->assertForbidden();
});
