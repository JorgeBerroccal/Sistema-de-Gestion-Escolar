<?php

use App\Livewire\Matricula\Alumnos\Show;
use App\Matricula\AlumnoService;
use App\Matricula\MatriculaIncumplida;
use App\Matricula\Parentesco;
use App\Models\Alumno;
use App\Models\Matricula;
use Livewire\Livewire;

function usuarioAlumnoApto()
{
    return usuarioConPermisos(['matricula.ver_propia', 'matricula.ser_alumno']);
}

function usuarioApoderadoApto()
{
    return usuarioConPermisos(['matricula.ver_propia', 'matricula.ser_apoderado']);
}

it('vincula un usuario con matricula.ser_alumno activo a una ficha', function () {
    $alumno = Alumno::factory()->create();
    $usuario = usuarioAlumnoApto();

    app(AlumnoService::class)->vincularUsuario($alumno, $usuario);

    expect($alumno->fresh()->usuario_id)->toBe($usuario->id);
});

it('rechaza vincular un usuario ya vinculado a otra ficha, uno sin el permiso o uno inactivo', function () {
    [$a, $b] = Alumno::factory()->count(2)->create();
    $servicio = app(AlumnoService::class);
    $usuario = usuarioAlumnoApto();
    $servicio->vincularUsuario($a, $usuario);

    expect(fn () => $servicio->vincularUsuario($b, $usuario))->toThrow(MatriculaIncumplida::class, 'otra ficha');
    expect(fn () => $servicio->vincularUsuario($b, usuarioConPermisos(['matricula.ver_propia'])))->toThrow(MatriculaIncumplida::class, 'no permite ser alumno');
    expect(fn () => $servicio->vincularUsuario($b, usuarioConPermisos(['matricula.ser_alumno'], ['activo' => false])))->toThrow(MatriculaIncumplida::class, 'desactivado');
    expect($b->fresh()->usuario_id)->toBeNull();
});

it('rechaza vincular un segundo usuario a una ficha que ya tiene uno', function () {
    $alumno = Alumno::factory()->create();
    $servicio = app(AlumnoService::class);
    $servicio->vincularUsuario($alumno, usuarioAlumnoApto());

    expect(fn () => $servicio->vincularUsuario($alumno, usuarioAlumnoApto()))->toThrow(MatriculaIncumplida::class, 'ya tiene un usuario');
});

it('quita el vínculo de usuario sin tocar las matrículas', function () {
    $alumno = Alumno::factory()->create();
    Matricula::factory()->create(['alumno_id' => $alumno->id]);
    $servicio = app(AlumnoService::class);
    $servicio->vincularUsuario($alumno, usuarioAlumnoApto());

    $servicio->quitarUsuario($alumno);

    expect($alumno->fresh()->usuario_id)->toBeNull()->and($alumno->matriculas()->count())->toBe(1);
});

it('vincula apoderados con parentesco, un apoderado con varios hijos', function () {
    [$hijo1, $hijo2] = Alumno::factory()->count(2)->create();
    $madre = usuarioApoderadoApto();
    $servicio = app(AlumnoService::class);

    $servicio->agregarApoderado($hijo1, $madre, Parentesco::Madre);
    $servicio->agregarApoderado($hijo2, $madre, 'tutor');
    $servicio->agregarApoderado($hijo1, usuarioApoderadoApto(), 'padre');

    expect($hijo1->apoderados()->count())->toBe(2)
        ->and($hijo2->apoderados()->first()->pivot->parentesco)->toBe('tutor')
        ->and($madre->id)->toBe($hijo1->apoderados()->where('parentesco', 'madre')->first()->id);
});

it('no repite al mismo apoderado en el alumno y exige parentesco válido', function () {
    $alumno = Alumno::factory()->create();
    $padre = usuarioApoderadoApto();
    $servicio = app(AlumnoService::class);
    $servicio->agregarApoderado($alumno, $padre, 'padre');

    expect(fn () => $servicio->agregarApoderado($alumno, $padre, 'madre'))->toThrow(MatriculaIncumplida::class, 'ya es apoderado');
    expect(fn () => $servicio->agregarApoderado($alumno, usuarioApoderadoApto(), 'abuelo'))->toThrow(MatriculaIncumplida::class, 'parentesco');
    expect($alumno->apoderados()->count())->toBe(1);
});

it('rechaza como apoderado a un usuario sin matricula.ser_apoderado (por ejemplo un docente) o inactivo', function () {
    $alumno = Alumno::factory()->create();
    $servicio = app(AlumnoService::class);

    expect(fn () => $servicio->agregarApoderado($alumno, usuarioConPermisos(['docencia.dictar']), 'tutor'))->toThrow(MatriculaIncumplida::class, 'no permite ser apoderado');
    expect(fn () => $servicio->agregarApoderado($alumno, usuarioConPermisos(['matricula.ser_apoderado'], ['activo' => false]), 'tutor'))->toThrow(MatriculaIncumplida::class, 'desactivado');
    expect($alumno->apoderados()->count())->toBe(0);
});

it('quitar un apoderado conserva a los demás y las matrículas', function () {
    $alumno = Alumno::factory()->create();
    Matricula::factory()->create(['alumno_id' => $alumno->id]);
    [$uno, $dos] = [usuarioApoderadoApto(), usuarioApoderadoApto()];
    $servicio = app(AlumnoService::class);
    $servicio->agregarApoderado($alumno, $uno, 'madre');
    $servicio->agregarApoderado($alumno, $dos, 'padre');

    $servicio->quitarApoderado($alumno, $uno);

    expect($alumno->apoderados()->pluck('usuarios.id')->all())->toBe([$dos->id])->and($alumno->matriculas()->count())->toBe(1);
});

it('lista solo usuarios elegibles', function () {
    $alumno = Alumno::factory()->create();
    $apto = usuarioApoderadoApto();
    usuarioConPermisos(['matricula.ser_apoderado'], ['activo' => false]);
    usuarioConPermisos(['docencia.dictar']);
    $servicio = app(AlumnoService::class);

    expect($servicio->apoderadosElegibles($alumno)->pluck('id')->all())->toBe([$apto->id]);

    $servicio->agregarApoderado($alumno, $apto, 'tutor');
    expect($servicio->apoderadosElegibles($alumno)->count())->toBe(0);
});

it('desde la ficha el gestor vincula y quita usuario y apoderados', function () {
    $alumno = Alumno::factory()->create();
    $usuario = usuarioAlumnoApto();
    $padre = usuarioApoderadoApto();
    $gestor = usuarioConPermisos(['matricula.ver_todas', 'matricula.gestionar']);

    $ficha = Livewire::actingAs($gestor)->test(Show::class, ['alumno' => $alumno]);
    $ficha->set('usuarioAlumnoId', (string) $usuario->id)->call('vincularUsuario')->assertHasNoErrors();
    $ficha->set('apoderadoId', (string) $padre->id)->set('parentesco', 'madre')->call('agregarApoderado')->assertHasNoErrors();
    expect($alumno->fresh()->usuario_id)->toBe($usuario->id)->and($alumno->apoderados()->count())->toBe(1);

    $ficha->call('quitarApoderado', $padre->id)->call('quitarUsuario');
    expect($alumno->fresh()->usuario_id)->toBeNull()->and($alumno->apoderados()->count())->toBe(0);
});

it('el Director ve los vínculos pero no puede modificarlos', function () {
    $alumno = Alumno::factory()->create();
    $padre = usuarioApoderadoApto();
    app(AlumnoService::class)->agregarApoderado($alumno, $padre, 'madre');
    $director = usuarioConPermisos(['matricula.ver_todas']);

    Livewire::actingAs($director)->test(Show::class, ['alumno' => $alumno])->assertSee($padre->username)->assertDontSee('Agregar apoderado');
    Livewire::actingAs($director)->test(Show::class, ['alumno' => $alumno])->set('apoderadoId', (string) usuarioApoderadoApto()->id)->set('parentesco', 'tutor')->call('agregarApoderado')->assertForbidden();
    expect($alumno->apoderados()->count())->toBe(1);
});
