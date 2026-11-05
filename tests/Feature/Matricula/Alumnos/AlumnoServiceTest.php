<?php

use App\Matricula\AlumnoService;
use App\Matricula\MatriculaIncumplida;
use App\Models\Alumno;
use App\Models\Matricula;
use App\Models\TipoDocumento;
use Database\Seeders\TiposDocumentoSeeder;
use Illuminate\Database\UniqueConstraintViolationException;

beforeEach(fn () => $this->seed(TiposDocumentoSeeder::class));

function datosAlumno(array $cambios = []): array
{
    return array_merge([
        'nombres' => 'Alumno Prueba',
        'apellidos' => 'Ficticio Ejemplo',
        'tipo_documento_id' => TipoDocumento::where('codigo', 'DNI')->value('id'),
        'numero_documento' => '90000001',
        'fecha_nacimiento' => '2015-04-10',
        'sexo' => 'F',
    ], $cambios);
}

it('crea un alumno válido, activo y con el documento normalizado', function () {
    $alumno = app(AlumnoService::class)->crear(datosAlumno(['nombres' => '  Alumno   Prueba ', 'numero_documento' => ' 90000001 ']));

    expect($alumno->exists)->toBeTrue()
        ->and($alumno->activo)->toBeTrue()
        ->and($alumno->nombres)->toBe('Alumno Prueba')
        ->and($alumno->numero_documento)->toBe('90000001');
});

it('rechaza un DNI que no tiene 8 dígitos', function (string $numero) {
    expect(fn () => app(AlumnoService::class)->crear(datosAlumno(['numero_documento' => $numero])))
        ->toThrow(MatriculaIncumplida::class);
    expect(Alumno::count())->toBe(0);
})->with(['7 dígitos' => '9000000', '9 dígitos' => '900000011', 'con letra' => '9000000A', 'vacío' => '']);

it('rechaza un documento repetido con el mismo tipo y número, y acepta el mismo número con otro tipo', function () {
    $servicio = app(AlumnoService::class);
    $servicio->crear(datosAlumno());

    expect(fn () => $servicio->crear(datosAlumno(['nombres' => 'Otro'])))->toThrow(MatriculaIncumplida::class, 'Ya existe un alumno');

    $otro = $servicio->crear(datosAlumno(['tipo_documento_id' => TipoDocumento::where('codigo', 'OTRO')->value('id')]));
    expect($otro->exists)->toBeTrue()->and(Alumno::count())->toBe(2);
});

it('la unicidad también la impone la base de datos', function () {
    Alumno::factory()->create(['numero_documento' => '90000002']);

    expect(fn () => Alumno::factory()->create(['numero_documento' => '90000002']))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('rechaza fecha de nacimiento futura, nombres vacíos, sexo y tipo inválidos', function (array $cambios) {
    expect(fn () => app(AlumnoService::class)->crear(datosAlumno($cambios)))->toThrow(MatriculaIncumplida::class);
})->with([
    'fecha futura' => [['fecha_nacimiento' => '2999-01-01']],
    'fecha inválida' => [['fecha_nacimiento' => 'ayer']],
    'nombres vacíos' => [['nombres' => '   ']],
    'apellidos vacíos' => [['apellidos' => '']],
    'sexo inválido' => [['sexo' => 'X']],
    'tipo inexistente' => [['tipo_documento_id' => 9999]],
]);

it('el mensaje de error no incluye nombres ni documentos', function () {
    $servicio = app(AlumnoService::class);
    $servicio->crear(datosAlumno());

    try {
        $servicio->crear(datosAlumno());
    } catch (MatriculaIncumplida $e) {
        expect($e->getMessage())->not->toContain('Alumno Prueba')->not->toContain('90000001');
    }
});

it('al actualizar no choca consigo mismo y sí con otro alumno', function () {
    $servicio = app(AlumnoService::class);
    $a = $servicio->crear(datosAlumno());
    $b = $servicio->crear(datosAlumno(['numero_documento' => '90000003']));

    $servicio->actualizar($a, datosAlumno(['nombres' => 'Cambiado']));
    expect($a->fresh()->nombres)->toBe('Cambiado');

    expect(fn () => $servicio->actualizar($b, datosAlumno(['numero_documento' => '90000001'])))->toThrow(MatriculaIncumplida::class);
});

it('elimina a un alumno sin matrículas y solo desactiva al que tiene', function () {
    $servicio = app(AlumnoService::class);
    $libre = Alumno::factory()->create();
    $conMatricula = Alumno::factory()->create();
    Matricula::factory()->retirada()->create(['alumno_id' => $conMatricula->id]);

    $servicio->eliminar($libre);
    expect(Alumno::find($libre->id))->toBeNull();

    expect(fn () => $servicio->eliminar($conMatricula))->toThrow(MatriculaIncumplida::class, 'solo desactivar');
    $servicio->desactivar($conMatricula);
    expect($conMatricula->fresh()->activo)->toBeFalse();
    $servicio->reactivar($conMatricula);
    expect($conMatricula->fresh()->activo)->toBeTrue();
});

it('no desactiva a un alumno con matrícula activa', function () {
    $alumno = Alumno::factory()->create();
    Matricula::factory()->create(['alumno_id' => $alumno->id]);

    expect(fn () => app(AlumnoService::class)->desactivar($alumno))->toThrow(MatriculaIncumplida::class, 'matrícula activa');
    expect($alumno->fresh()->activo)->toBeTrue();
});
