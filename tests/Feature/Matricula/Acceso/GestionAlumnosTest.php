<?php

use App\Livewire\Matricula\Alumnos\Form;
use App\Livewire\Matricula\Alumnos\Index;
use App\Models\Alumno;
use App\Models\TipoDocumento;
use Database\Seeders\TiposDocumentoSeeder;
use Livewire\Livewire;

beforeEach(fn () => $this->seed(TiposDocumentoSeeder::class));

function gestorAlumnos()
{
    return usuarioConPermisos(['matricula.ver_todas', 'matricula.gestionar']);
}

it('con matricula.gestionar crea, edita y desactiva alumnos desde la interfaz', function () {
    $gestor = gestorAlumnos();
    $dni = TipoDocumento::where('codigo', 'DNI')->value('id');

    Livewire::actingAs($gestor)->test(Form::class)
        ->set('nombres', 'Alumna Prueba')->set('apellidos', 'Ficticia Ejemplo')->set('tipo_documento_id', (string) $dni)
        ->set('numero_documento', '90000010')->set('fecha_nacimiento', '2014-03-02')->set('sexo', 'F')
        ->call('guardar')->assertHasNoErrors();

    $alumno = Alumno::firstOrFail();

    Livewire::actingAs($gestor)->test(Form::class, ['alumno' => $alumno])->set('nombres', 'Nombre Editado')->call('guardar')->assertHasNoErrors();
    expect($alumno->fresh()->nombres)->toBe('Nombre Editado');

    Livewire::actingAs($gestor)->test(Index::class)->call('desactivar', $alumno->id);
    expect($alumno->fresh()->activo)->toBeFalse();
});

it('muestra el error de un DNI inválido en el formulario', function () {
    Livewire::actingAs(gestorAlumnos())->test(Form::class)
        ->set('nombres', 'A')->set('apellidos', 'B')->set('tipo_documento_id', (string) TipoDocumento::where('codigo', 'DNI')->value('id'))
        ->set('numero_documento', '123')->set('fecha_nacimiento', '2014-03-02')->set('sexo', 'M')
        ->call('guardar')->assertHasErrors('general');

    expect(Alumno::count())->toBe(0);
});

it('con solo matricula.ver_todas (Director) las rutas de gestión y las acciones dan 403', function () {
    $director = usuarioConPermisos(['matricula.ver_todas']);
    $alumno = Alumno::factory()->create();

    $this->actingAs($director)->get(route('matricula.alumnos.index'))->assertOk();
    $this->actingAs($director)->get(route('matricula.alumnos.create'))->assertForbidden();
    $this->actingAs($director)->get(route('matricula.alumnos.edit', $alumno))->assertForbidden();

    Livewire::actingAs($director)->test(Index::class)->call('desactivar', $alumno->id)->assertForbidden();
    Livewire::actingAs($director)->test(Index::class)->call('eliminar', $alumno->id)->assertForbidden();
    expect($alumno->fresh()->activo)->toBeTrue();
});

it('un rol sin permisos de matrícula recibe 403 en todo el módulo', function () {
    $sinPermiso = usuarioConPermisos([]);
    $alumno = Alumno::factory()->create();

    foreach ([route('matricula.alumnos.index'), route('matricula.alumnos.create'), route('matricula.alumnos.edit', $alumno), route('matricula.nominas.index')] as $url) {
        $this->actingAs($sinPermiso)->get($url)->assertForbidden();
    }
});

it('sin sesión redirige al inicio de sesión', function () {
    $this->get(route('matricula.alumnos.index'))->assertRedirect(route('login'));
});
