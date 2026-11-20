<?php

use App\Asistente\ResolverAlumno;
use App\Livewire\Asistencia\Propia\Index as MiAsistencia;
use App\Livewire\Asistente\Chat\Index as Chat;
use App\Livewire\Evaluacion\Propia\Index as MisNotas;
use App\Livewire\Matricula\MisAlumnos\Index as MiFicha;
use App\Models\Alumno;
use App\Models\Matricula;
use Livewire\Livewire;

const AVISO_ALUMNO = 'Tu cuenta todavía no está vinculada a tu ficha de alumno. Comunícate con la administración del colegio.';
const AVISO_APODERADO = 'Tu cuenta todavía no tiene estudiantes vinculados. Comunícate con la administración del colegio.';

/** R-08: textos por permiso de vínculo (nunca por nombre de rol) y aviso claro sin ficha o sin estudiantes. */
function pantallasPropias(): array
{
    return [MiFicha::class, MiAsistencia::class, MisNotas::class, Chat::class];
}

it('el alumno sin ficha vinculada ve el aviso en todas sus pantallas, sin tabla ni Acciones', function () {
    $alumno = usuarioConRolDeSeeder('Alumno');

    foreach (pantallasPropias() as $pantalla) {
        Livewire::actingAs($alumno)->test($pantalla)->assertSee(AVISO_ALUMNO)->assertDontSee('Acciones')->assertDontSee('No tienes alumnos vinculados');
    }
});

it('el apoderado sin estudiantes ve su propio aviso en todas sus pantallas', function () {
    $padre = usuarioConRolDeSeeder('Padre de Familia');

    foreach (pantallasPropias() as $pantalla) {
        Livewire::actingAs($padre)->test($pantalla)->assertSee(AVISO_APODERADO)->assertDontSee(AVISO_ALUMNO);
    }
});

it('el asistente responde con el mismo aviso cuando no hay ficha vinculada', function () {
    $alumno = usuarioConRolDeSeeder('Alumno');

    expect(app(ResolverAlumno::class)->resolver($alumno, 'hola')->sinDatos)->toBe(AVISO_ALUMNO);
});

it('«Mi ficha» del alumno con ficha es una tarjeta con sus datos y su matrícula vigente, sin Acciones', function () {
    ['seccion' => $seccion] = escenarioMatricula();
    $usuario = usuarioConRolDeSeeder('Alumno');
    $ficha = Alumno::factory()->create(['nombres' => 'Alumna Propia', 'apellidos' => 'Ficticia Prueba', 'usuario_id' => $usuario->id]);
    Matricula::factory()->create(['alumno_id' => $ficha->id, 'seccion_id' => $seccion->id]);

    Livewire::actingAs($usuario)->test(MiFicha::class)
        ->assertSee('Mi ficha')->assertSee('Alumna Propia')->assertSee($ficha->numero_documento)
        ->assertSee($seccion->grado->nombre)->assertSeeHtml('data-ficha-propia')
        ->assertDontSee('Acciones')->assertDontSee('Ver ficha e historial')->assertDontSee(AVISO_ALUMNO);
    Livewire::actingAs($usuario)->test(MiAsistencia::class)->assertDontSee(AVISO_ALUMNO);
    Livewire::actingAs($usuario)->test(MisNotas::class)->assertDontSee(AVISO_ALUMNO);
});

it('el apoderado con estudiantes ve la lista con Acciones y el título de estudiantes a cargo', function () {
    ['seccion' => $seccion] = escenarioMatricula();
    $padre = usuarioConRolDeSeeder('Padre de Familia');
    $hijo = Alumno::factory()->create(['nombres' => 'Hijo Visible', 'apellidos' => 'Ficticio Prueba']);
    Matricula::factory()->create(['alumno_id' => $hijo->id, 'seccion_id' => $seccion->id]);
    $hijo->apoderados()->attach($padre->id, ['parentesco' => 'padre']);

    Livewire::actingAs($padre)->test(MiFicha::class)
        ->assertSee('Estudiantes a mi cargo')->assertSee('Hijo Visible')->assertSee('Acciones')
        ->assertDontSeeHtml('data-ficha-propia')->assertDontSee(AVISO_APODERADO);
});

it('el texto depende del permiso de vínculo y no del nombre del rol', function () {
    $conPermisoAlumno = usuarioConPermisos(['matricula.ver_propia', 'matricula.ser_alumno', 'asistencia.ver_propia']);
    $conPermisoApoderado = usuarioConPermisos(['matricula.ver_propia', 'matricula.ser_apoderado', 'asistencia.ver_propia']);

    Livewire::actingAs($conPermisoAlumno)->test(MiAsistencia::class)->assertSee(AVISO_ALUMNO);
    Livewire::actingAs($conPermisoApoderado)->test(MiAsistencia::class)->assertSee(AVISO_APODERADO);
});
