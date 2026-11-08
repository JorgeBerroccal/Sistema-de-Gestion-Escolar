<?php

use App\Asistencia\AsistenciaIncumplida;
use App\Asistencia\AsistenciaService;
use App\Models\Alumno;
use App\Models\Asistencia;
use App\Models\EstadoAsistencia;
use App\Models\Grado;
use App\Models\Matricula;
use App\Models\Seccion;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

function filasDe($alumnos, string $codigo = 'presente'): array
{
    $id = EstadoAsistencia::where('codigo', $codigo)->value('id');

    return $alumnos->mapWithKeys(fn ($a) => [$a->id => ['estado_id' => $id]])->all();
}

it('lista solo a los alumnos con matrícula vigente en la fecha', function () {
    ['seccion' => $seccion, 'alumnos' => $alumnos] = escenarioAsistencia(2);
    $dia = diaHabilReciente();
    $tarde = Alumno::factory()->create();
    Matricula::factory()->create(['alumno_id' => $tarde->id, 'seccion_id' => $seccion->id, 'fecha_matricula' => $dia->addDay()->toDateString()]);
    $retirado = Alumno::factory()->create();
    Matricula::factory()->retirada()->create([
        'alumno_id' => $retirado->id, 'seccion_id' => $seccion->id,
        'fecha_matricula' => $dia->subMonth()->toDateString(), 'fecha_estado' => $dia->toDateString(),
    ]);
    $servicio = app(AsistenciaService::class);

    // El día del retiro ya no figura; el día anterior sí. El matriculado después no figura aún.
    expect($servicio->nominaDeLaFecha($seccion, $dia)->pluck('id')->all())->toEqualCanonicalizing($alumnos->pluck('id')->all())
        ->and($servicio->nominaDeLaFecha($seccion, $dia->subDay())->pluck('id')->all())->toContain($retirado->id)
        ->and($servicio->nominaDeLaFecha($seccion, $dia->addDay())->pluck('id')->all())->toContain($tarde->id);
});

it('un alumno trasladado el día D aparece hasta D-1 en el origen y desde D en el destino', function () {
    ['seccion' => $origen, 'anio' => $anio] = escenarioAsistencia(0);
    $destino = Seccion::factory()->for(Grado::factory()->create())->for($anio, 'anio')->create(['identificador' => 'B']);
    $dia = diaHabilReciente();
    $alumno = Alumno::factory()->create();
    Matricula::factory()->create(['alumno_id' => $alumno->id, 'seccion_id' => $origen->id, 'estado' => 'trasladado', 'fecha_matricula' => $dia->subMonth()->toDateString(), 'fecha_estado' => $dia->toDateString()]);
    Matricula::factory()->create(['alumno_id' => $alumno->id, 'seccion_id' => $destino->id, 'fecha_matricula' => $dia->toDateString()]);
    $servicio = app(AsistenciaService::class);

    expect($servicio->nominaDeLaFecha($origen, $dia->subDay())->pluck('id')->all())->toBe([$alumno->id])
        ->and($servicio->nominaDeLaFecha($origen, $dia)->pluck('id')->all())->toBe([])
        ->and($servicio->nominaDeLaFecha($destino, $dia->subDay())->pluck('id')->all())->toBe([])
        ->and($servicio->nominaDeLaFecha($destino, $dia)->pluck('id')->all())->toBe([$alumno->id]);
});

it('guarda un registro por alumno y volver a guardar reemplaza sin duplicar', function () {
    ['seccion' => $seccion, 'alumnos' => $alumnos, 'docente' => $docente] = escenarioAsistencia(3);
    $dia = diaHabilReciente();
    $servicio = app(AsistenciaService::class);

    expect($servicio->guardar($seccion, $dia, filasDe($alumnos), $docente))->toBe(3);
    $filas = filasDe($alumnos);
    $filas[$alumnos[0]->id] = ['estado_id' => EstadoAsistencia::where('codigo', 'falta')->value('id'), 'observacion' => '  Enfermo '];
    $servicio->guardar($seccion, $dia, $filas, $docente);

    $registros = Asistencia::with('estado')->where('seccion_id', $seccion->id)->get()->keyBy('alumno_id');
    expect($registros)->toHaveCount(3)
        ->and($registros[$alumnos[0]->id]->estado->codigo)->toBe('falta')
        ->and($registros[$alumnos[0]->id]->observacion)->toBe('Enfermo')
        ->and($registros[$alumnos[0]->id]->registrado_por)->toBe($docente->id);
});

it('el último guardado prevalece sin duplicados (dos docentes casi a la vez)', function () {
    ['seccion' => $seccion, 'alumnos' => $alumnos, 'docente' => $docente, 'admin' => $admin] = escenarioAsistencia(2);
    $dia = diaHabilReciente();
    $servicio = app(AsistenciaService::class);

    $servicio->guardar($seccion, $dia, filasDe($alumnos, 'falta'), $docente);
    $servicio->guardar($seccion, $dia, filasDe($alumnos, 'tarde'), $admin);

    expect(Asistencia::count())->toBe(2)
        ->and(Asistencia::with('estado')->get()->pluck('estado.codigo')->unique()->all())->toBe(['tarde']);
});

it('es todo o nada: un estado inválido no guarda ninguno y no revela datos personales', function () {
    ['seccion' => $seccion, 'alumnos' => $alumnos, 'docente' => $docente] = escenarioAsistencia(3);
    $filas = filasDe($alumnos);
    $filas[$alumnos[2]->id] = ['estado_id' => 999999];

    try {
        app(AsistenciaService::class)->guardar($seccion, diaHabilReciente(), $filas, $docente);
        $this->fail('Debió rechazar.');
    } catch (AsistenciaIncumplida $e) {
        expect($e->getMessage())->toContain('Alumno 3 de la lista')->not->toContain($alumnos[2]->apellidos);
    }

    expect(Asistencia::count())->toBe(0);
});

it('rechaza un alumno ajeno a la sección y una observación demasiado larga', function () {
    ['seccion' => $seccion, 'alumnos' => $alumnos, 'docente' => $docente] = escenarioAsistencia(1);
    $servicio = app(AsistenciaService::class);
    $presente = EstadoAsistencia::where('codigo', 'presente')->value('id');

    expect(fn () => $servicio->guardar($seccion, diaHabilReciente(), [Alumno::factory()->create()->id => ['estado_id' => $presente]], $docente))
        ->toThrow(AsistenciaIncumplida::class, 'no pertenece');
    expect(fn () => $servicio->guardar($seccion, diaHabilReciente(), [$alumnos[0]->id => ['estado_id' => $presente, 'observacion' => str_repeat('x', 256)]], $docente))
        ->toThrow(AsistenciaIncumplida::class, '255');
    expect(fn () => $servicio->guardar($seccion, diaHabilReciente(), [], $docente))->toThrow(AsistenciaIncumplida::class);
});

it('rechaza fecha futura, sábado, domingo, fuera del año y año no vigente', function () {
    ['seccion' => $seccion, 'alumnos' => $alumnos, 'docente' => $docente, 'anio' => $anio] = escenarioAsistencia(1);
    $servicio = app(AsistenciaService::class);
    $filas = filasDe($alumnos);
    $habil = diaHabilReciente();
    $sabado = $habil->next('saturday')->isFuture() ? $habil->previous('saturday') : $habil->next('saturday');
    $domingo = $habil->previous('sunday');

    foreach ([
        'sábados ni los domingos' => $sabado->previous('saturday'),
        'domingos' => $domingo,
        'futura' => CarbonImmutable::parse(now(config('asistencia.zona_horaria'))->toDateString())->addDays(3)->next('wednesday'),
        'fuera del año' => $habil->subYears(3)->next('monday'),
    ] as $texto => $fecha) {
        expect(fn () => $servicio->guardar($seccion, $fecha, $filas, $docente))->toThrow(AsistenciaIncumplida::class, $texto);
    }

    $anio->forceFill(['vigente' => null])->save();
    expect(fn () => $servicio->guardar($seccion->fresh(), $habil, $filas, $docente))->toThrow(AsistenciaIncumplida::class, 'vigente');
    expect(Asistencia::count())->toBe(0);
});

it('a las 20:00 en Lima «hoy» sigue siendo el día de Lima y mañana se rechaza', function () {
    ['seccion' => $seccion, 'alumnos' => $alumnos, 'docente' => $docente] = escenarioAsistencia(1);
    config(['asistencia.zona_horaria' => 'America/Lima']);
    // Lunes 02:00 UTC = domingo 21:00 en Lima; usamos un martes 01:00 UTC = lunes 20:00 en Lima.
    $this->travelTo(CarbonImmutable::parse('2026-11-10 01:00:00', 'UTC')); // lunes 9/11 20:00 Lima
    $servicio = app(AsistenciaService::class);

    expect($servicio->hoy())->toBe('2026-11-09');
    $servicio->guardar($seccion, CarbonImmutable::parse('2026-11-09'), filasDe($alumnos), $docente);
    expect(Asistencia::count())->toBe(1)
        ->and(fn () => $servicio->guardar($seccion, CarbonImmutable::parse('2026-11-10'), filasDe($alumnos), $docente))
        ->toThrow(AsistenciaIncumplida::class, 'futura');
});

it('una sección sin alumnos no permite guardar', function () {
    ['seccion' => $seccion, 'docente' => $docente] = escenarioAsistencia(0);

    expect(fn () => app(AsistenciaService::class)->guardar($seccion, diaHabilReciente(), [1 => ['estado_id' => 1]], $docente))
        ->toThrow(AsistenciaIncumplida::class, 'no tiene alumnos');
});

it('el índice único impide dos registros del mismo alumno, sección y fecha', function () {
    ['seccion' => $seccion, 'alumnos' => $alumnos, 'docente' => $docente] = escenarioAsistencia(1);
    $datos = ['seccion_id' => $seccion->id, 'alumno_id' => $alumnos[0]->id, 'fecha' => diaHabilReciente()->toDateString(), 'estado_id' => EstadoAsistencia::first()->id, 'registrado_por' => $docente->id];
    Asistencia::create($datos);

    expect(fn () => DB::transaction(fn () => Asistencia::create($datos)))->toThrow(UniqueConstraintViolationException::class);
});
