<?php

use App\Livewire\Matricula\Alumnos\Index as IndexAlumnos;
use App\Livewire\Matricula\Alumnos\Show as FichaAlumno;
use App\Livewire\Matricula\MisAlumnos\Index as MisAlumnos;
use App\Livewire\Matricula\Nominas\Index;
use App\Livewire\Matricula\Nominas\Show as Nomina;
use App\Models\Alumno;
use App\Models\AsignacionDocente;
use App\Models\Matricula;
use App\Models\Seccion;
use Livewire\Livewire;

/** Escenario: dos secciones con un alumno cada una; el docente solo dicta en la primera. */
function escenarioPorRol(): array
{
    ['seccion' => $propia, 'anio' => $anio] = escenarioMatricula();
    $ajena = Seccion::factory()->for($propia->grado)->for($anio, 'anio')->create(['identificador' => 'B']);
    $hijo = Alumno::factory()->create(['nombres' => 'Hijo Visible', 'apellidos' => 'Propio Ficticio']);
    $ajeno = Alumno::factory()->create(['nombres' => 'Otro Oculto', 'apellidos' => 'Ajeno Ficticio']);
    Matricula::factory()->create(['alumno_id' => $hijo->id, 'seccion_id' => $propia->id]);
    Matricula::factory()->create(['alumno_id' => $ajeno->id, 'seccion_id' => $ajena->id]);

    return compact('propia', 'ajena', 'hijo', 'ajeno');
}

it('Administrador gestiona y Director consulta todo sin poder modificar', function () {
    ['hijo' => $hijo, 'ajeno' => $ajeno] = escenarioPorRol();
    $admin = usuarioConRolDeSeeder('Administrador');
    $director = usuarioConRolDeSeeder('Director');

    foreach ([$admin, $director] as $usuario) {
        Livewire::actingAs($usuario)->test(IndexAlumnos::class)->assertSee('Hijo Visible')->assertSee('Otro Oculto');
        $this->actingAs($usuario)->get(route('matricula.alumnos.show', $ajeno))->assertOk();
    }

    $this->actingAs($admin)->get(route('matricula.alumnos.create'))->assertOk();
    $this->actingAs($director)->get(route('matricula.alumnos.create'))->assertForbidden();
    Livewire::actingAs($director)->test(FichaAlumno::class, ['alumno' => $hijo])->call('desactivar')->assertForbidden();
    expect($hijo->fresh()->activo)->toBeTrue();
});

it('el Docente ve solo las nóminas de sus secciones con nombres, apellidos y documento', function () {
    ['propia' => $propia, 'ajena' => $ajena, 'hijo' => $hijo, 'ajeno' => $ajeno] = escenarioPorRol();
    $docente = usuarioConRolDeSeeder('Docente');
    AsignacionDocente::factory()->create(['seccion_id' => $propia->id, 'usuario_id' => $docente->id]);

    Livewire::actingAs($docente)->test(Nomina::class, ['seccion' => $propia])
        ->assertSee('Hijo Visible')->assertSee($hijo->numero_documento)->assertDontSee('Ver ficha')->assertDontSee('Matricular seleccionados')
        ->assertDontSee($hijo->fecha_nacimiento->format('d/m/Y'));

    $this->actingAs($docente)->get(route('matricula.nominas.index'))->assertOk();
    $this->actingAs($docente)->get(route('matricula.nominas.show', $ajena))->assertForbidden()->assertDontSee('Otro Oculto');
    $this->actingAs($docente)->get(route('matricula.alumnos.show', $hijo))->assertForbidden();
    $this->actingAs($docente)->get(route('matricula.alumnos.index'))->assertForbidden();
    $this->actingAs($docente)->get(route('matricula.alumnos.show', $ajeno))->assertForbidden();
});

it('la lista de secciones del Docente muestra solo las suyas', function () {
    ['propia' => $propia, 'ajena' => $ajena] = escenarioPorRol();
    $docente = usuarioConRolDeSeeder('Docente');
    AsignacionDocente::factory()->create(['seccion_id' => $propia->id, 'usuario_id' => $docente->id]);

    Livewire::actingAs($docente)->test(Index::class)
        ->assertViewHas('secciones', fn ($p) => $p->pluck('id')->all() === [$propia->id]);
});

it('el Padre de Familia ve solo a sus hijos y 403 con alumnos ajenos y con las pantallas de gestión', function () {
    ['hijo' => $hijo, 'ajeno' => $ajeno, 'propia' => $propia] = escenarioPorRol();
    $padre = usuarioConRolDeSeeder('Padre de Familia');
    $hijo->apoderados()->attach($padre->id, ['parentesco' => 'madre']);

    Livewire::actingAs($padre)->test(MisAlumnos::class)->assertSee('Hijo Visible')->assertDontSee('Otro Oculto');
    $this->actingAs($padre)->get(route('matricula.alumnos.show', $hijo))->assertOk()->assertSee('Historial de matrículas');
    $this->actingAs($padre)->get(route('matricula.alumnos.show', $ajeno))->assertForbidden();
    foreach ([route('matricula.alumnos.index'), route('matricula.alumnos.create'), route('matricula.nominas.index'), route('matricula.nominas.show', $propia)] as $url) {
        $this->actingAs($padre)->get($url)->assertForbidden();
    }
});

it('el Padre ve la ficha de sus hijos sin acciones ni vínculos', function () {
    ['hijo' => $hijo] = escenarioPorRol();
    $padre = usuarioConRolDeSeeder('Padre de Familia');
    $hijo->apoderados()->attach($padre->id, ['parentesco' => 'padre']);

    Livewire::actingAs($padre)->test(FichaAlumno::class, ['alumno' => $hijo])
        ->assertDontSee('Retirar')->assertDontSee('Desactivar')->assertDontSee('Agregar apoderado')->assertDontSee('Apoderados');
    Livewire::actingAs($padre)->test(FichaAlumno::class, ['alumno' => $hijo])->call('desactivar')->assertForbidden();
});

it('el Alumno ve solo su ficha, sin nóminas ni compañeros', function () {
    ['hijo' => $hijo, 'ajeno' => $ajeno, 'propia' => $propia] = escenarioPorRol();
    $alumno = usuarioConRolDeSeeder('Alumno');
    $hijo->update(['usuario_id' => $alumno->id]);

    Livewire::actingAs($alumno)->test(MisAlumnos::class)->assertSee('Hijo Visible')->assertDontSee('Otro Oculto');
    $this->actingAs($alumno)->get(route('matricula.alumnos.show', $hijo))->assertOk();
    $this->actingAs($alumno)->get(route('matricula.alumnos.show', $ajeno))->assertForbidden();
    $this->actingAs($alumno)->get(route('matricula.nominas.show', $propia))->assertForbidden();
    $this->actingAs($alumno)->get(route('matricula.nominas.index'))->assertForbidden();
});

it('un usuario sin vínculos ve una lista vacía, nunca datos de otros', function () {
    escenarioPorRol();
    $sinVinculos = usuarioConRolDeSeeder('Padre de Familia');

    Livewire::actingAs($sinVinculos)->test(MisAlumnos::class)
        ->assertSee('No tienes alumnos vinculados')->assertDontSee('Hijo Visible')->assertDontSee('Otro Oculto');
});

it('el menú muestra a cada rol solo sus ítems de matrícula', function () {
    escenarioPorRol();
    $etiquetas = fn ($usuario) => collect(config('menu'))
        ->filter(fn ($i) => str_starts_with($i['ruta'], 'matricula.') && ($i['permiso'] === null || $usuario->tienePermiso($i['permiso'])))
        ->pluck('etiqueta')->all();

    expect($etiquetas(usuarioConRolDeSeeder('Administrador')))->toBe(['Alumnos', 'Nóminas'])
        ->and($etiquetas(usuarioConRolDeSeeder('Director')))->toBe(['Alumnos', 'Nóminas'])
        ->and($etiquetas(usuarioConRolDeSeeder('Docente')))->toBe(['Mis secciones'])
        ->and($etiquetas(usuarioConRolDeSeeder('Padre de Familia')))->toBe(['Estudiantes a mi cargo'])
        ->and($etiquetas(usuarioConRolDeSeeder('Alumno')))->toBe(['Mi ficha']);
});

it('el título de la pantalla propia depende del permiso de vínculo, no del rol', function () {
    $apoderado = usuarioConPermisos(['matricula.ver_propia', 'matricula.ser_apoderado']);
    $alumno = usuarioConPermisos(['matricula.ver_propia', 'matricula.ser_alumno']);
    $ambos = usuarioConPermisos(['matricula.ver_propia', 'matricula.ser_apoderado', 'matricula.ser_alumno']);

    Livewire::actingAs($apoderado)->test(MisAlumnos::class)->assertSee('Estudiantes a mi cargo')->assertDontSee('Mi ficha');
    Livewire::actingAs($alumno)->test(MisAlumnos::class)->assertSee('Mi ficha')->assertDontSee('Estudiantes a mi cargo');
    Livewire::actingAs($ambos)->test(MisAlumnos::class)->assertSee('Mis alumnos');

    $this->actingAs($apoderado)->get('/inicio')->assertSee('Estudiantes a mi cargo')->assertDontSee('Mi ficha')->assertDontSee('Mis alumnos');
    $this->actingAs($alumno)->get('/inicio')->assertSee('Mi ficha')->assertDontSee('Estudiantes a mi cargo');
});
