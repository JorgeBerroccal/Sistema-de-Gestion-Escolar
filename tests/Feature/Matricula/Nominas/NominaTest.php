<?php

use App\Livewire\Matricula\Nominas\Index;
use App\Livewire\Matricula\Nominas\Show;
use App\Models\Alumno;
use App\Models\Matricula;
use App\Models\Seccion;
use Livewire\Livewire;

function adminNominas()
{
    return usuarioConPermisos(['matricula.ver_todas', 'matricula.gestionar']);
}

it('lista las secciones del año vigente con su cantidad de matriculados', function () {
    ['seccion' => $seccion] = escenarioMatricula();
    Matricula::factory()->count(2)->create(['seccion_id' => $seccion->id]);
    Matricula::factory()->retirada()->create(['seccion_id' => $seccion->id]);
    Seccion::factory()->create();

    $html = Livewire::actingAs(adminNominas())->test(Index::class)->html();

    expect($html)->toContain('A')->toContain('Ver nómina');
    Livewire::actingAs(adminNominas())->test(Index::class)->assertViewHas('secciones', fn ($p) => $p->total() === 1 && $p->first()->matriculados_count === 2);
});

it('muestra la nómina paginada con búsqueda sin tildes ni mayúsculas y filtro de estado', function () {
    ['seccion' => $seccion] = escenarioMatricula();
    $ana = Alumno::factory()->create(['nombres' => 'Ángela María', 'apellidos' => 'Quispe Ñahui', 'numero_documento' => '90000050']);
    Matricula::factory()->create(['seccion_id' => $seccion->id, 'alumno_id' => $ana->id]);
    Matricula::factory()->retirada()->create(['seccion_id' => $seccion->id]);
    Matricula::factory()->count(16)->create(['seccion_id' => $seccion->id]);
    $admin = adminNominas();

    $nomina = Livewire::actingAs($admin)->test(Show::class, ['seccion' => $seccion]);
    $nomina->assertViewHas('matriculas', fn ($p) => $p->total() === 18 && $p->perPage() === 15 && $p->count() === 15);

    $nomina->set('busqueda', 'angela')->assertViewHas('matriculas', fn ($p) => $p->total() === 1);
    $nomina->set('busqueda', 'ÑAHUI')->assertViewHas('matriculas', fn ($p) => $p->total() === 1);
    $nomina->set('busqueda', '900000')->assertViewHas('matriculas', fn ($p) => $p->total() >= 1);
    $nomina->set('busqueda', 'zzzz-no-existe')->assertViewHas('matriculas', fn ($p) => $p->total() === 0)->assertSee('No hay alumnos que coincidan');

    $nomina->set('busqueda', '')->set('filtroEstado', 'retirado')->assertViewHas('matriculas', fn ($p) => $p->total() === 1);
});

it('matricula de forma individual y masiva desde la nómina', function () {
    ['seccion' => $seccion] = escenarioMatricula();
    [$a, $b, $c] = Alumno::factory()->count(3)->create();
    $nomina = Livewire::actingAs(adminNominas())->test(Show::class, ['seccion' => $seccion]);

    $nomina->set('seleccionados', [(string) $a->id])->call('matricularSeleccionados')->assertHasNoErrors();
    expect(Matricula::where('seccion_id', $seccion->id)->count())->toBe(1);

    $nomina->set('seleccionados', [(string) $b->id, (string) $c->id])->call('matricularSeleccionados')->assertHasNoErrors();
    expect(Matricula::where('seccion_id', $seccion->id)->count())->toBe(3);

    // El alumno ya matriculado no se vuelve a ofrecer como elegible.
    $nomina->assertViewHas('elegibles', fn ($e) => $e->isEmpty());
});

it('muestra el rechazo todo o nada de la masiva sin nombres de alumnos', function () {
    ['seccion' => $seccion] = escenarioMatricula();
    [$a, $b] = Alumno::factory()->count(2)->create();
    Matricula::factory()->create(['seccion_id' => $seccion->id, 'alumno_id' => $a->id]);

    Livewire::actingAs(adminNominas())->test(Show::class, ['seccion' => $seccion])
        ->set('seleccionados', [(string) $b->id, (string) $a->id])->call('matricularSeleccionados')
        ->assertHasErrors('general')->assertSee('No se matriculó a ninguno');

    expect(Matricula::count())->toBe(1);
});

it('la sección de un año que no es el vigente no ofrece matricular', function () {
    escenarioMatricula();
    $deOtroAnio = Seccion::factory()->create();

    Livewire::actingAs(adminNominas())->test(Show::class, ['seccion' => $deOtroAnio])
        ->assertViewHas('admiteMatricula', false)->assertDontSee('Matricular seleccionados');
});

it('sin año vigente la lista de secciones lo avisa', function () {
    Livewire::actingAs(adminNominas())->test(Index::class)->assertSee('No hay un año escolar vigente');
});
