<?php

// Recorrido automatizado del quickstart §2 con los componentes reales y los roles del seeder.

use App\Livewire\Matricula\Alumnos\Form;
use App\Livewire\Matricula\Alumnos\Show;
use App\Livewire\Matricula\MisAlumnos\Index as MisAlumnos;
use App\Livewire\Matricula\Nominas\Show as Nomina;
use App\Models\Alumno;
use App\Models\Matricula;
use App\Models\TipoDocumento;
use Database\Seeders\TiposDocumentoSeeder;
use Livewire\Livewire;

it('recorre el flujo principal: alta, rechazos, matrícula individual y masiva, retiro, vínculo y 403 de rol limitado', function () {
    $this->seed(TiposDocumentoSeeder::class);
    ['seccion' => $seccion] = escenarioMatricula();
    $admin = usuarioConRolDeSeeder('Administrador');
    $padre = usuarioConRolDeSeeder('Padre de Familia');
    $dni = (string) TipoDocumento::where('codigo', 'DNI')->value('id');

    // 1. El menú del Administrador incluye Alumnos y Nóminas.
    $this->actingAs($admin)->get(route('matricula.alumnos.index'))->assertOk()->assertSee('Alumnos')->assertSee('Nóminas');

    // 2. DNI de 7 dígitos se rechaza; con 8 se guarda.
    $formulario = fn (string $numero, string $nombres) => Livewire::actingAs($admin)->test(Form::class)
        ->set('nombres', $nombres)->set('apellidos', 'Ficticio Ejemplo')->set('tipo_documento_id', $dni)
        ->set('numero_documento', $numero)->set('fecha_nacimiento', '2014-06-01')->set('sexo', 'F')->call('guardar');
    $formulario('9000001', 'Uno Demo')->assertHasErrors('general');
    $formulario('90000001', 'Uno Demo')->assertHasNoErrors();

    // 3. El mismo DNI se rechaza.
    $formulario('90000001', 'Repetido Demo')->assertHasErrors('general');
    expect(Alumno::count())->toBe(1);

    // 4. Matrícula individual y masiva; la nómina muestra los tres.
    $formulario('90000002', 'Dos Demo')->assertHasNoErrors();
    $formulario('90000003', 'Tres Demo')->assertHasNoErrors();
    [$uno, $dos, $tres] = Alumno::orderBy('id')->get()->all();
    Livewire::actingAs($admin)->test(Show::class, ['alumno' => $uno])->set('seccionId', (string) $seccion->id)->call('matricular')->assertHasNoErrors();
    $nomina = Livewire::actingAs($admin)->test(Nomina::class, ['seccion' => $seccion]);
    $nomina->set('seleccionados', [(string) $dos->id, (string) $tres->id])->call('matricularSeleccionados')->assertHasNoErrors();
    $nomina->assertSee('Uno Demo')->assertSee('Dos Demo')->assertSee('Tres Demo');

    // 5. Retirar a uno con motivo: sale de la nómina activa pero sigue en su historial.
    $matricula = Matricula::where('alumno_id', $tres->id)->firstOrFail();
    $ficha = Livewire::actingAs($admin)->test(Show::class, ['alumno' => $tres]);
    $ficha->call('iniciarCambio', $matricula->id, 'retirar')->set('cambioMotivo', 'Motivo de prueba')->call('confirmarCambio')->assertHasNoErrors();
    $ficha->assertSee('Retirado')->assertSee('Motivo de prueba');
    expect(Matricula::activas()->where('seccion_id', $seccion->id)->count())->toBe(2);

    // 6. Vincular un Padre de Familia a un alumno.
    $ficha = Livewire::actingAs($admin)->test(Show::class, ['alumno' => $uno]);
    $ficha->set('apoderadoId', (string) $padre->id)->set('parentesco', 'madre')->call('agregarApoderado')->assertHasNoErrors();
    $ficha->assertSee($padre->username);

    // 7. El rol limitado ve solo lo suyo; lo ajeno da 403.
    Livewire::actingAs($padre)->test(MisAlumnos::class)->assertSee('Uno Demo')->assertDontSee('Dos Demo');
    $this->actingAs($padre)->get(route('matricula.alumnos.show', $uno))->assertOk();
    $this->actingAs($padre)->get(route('matricula.alumnos.show', $dos))->assertForbidden();
    $this->actingAs($padre)->get(route('matricula.nominas.show', $seccion))->assertForbidden();
});
