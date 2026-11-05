<?php

use App\Matricula\AlumnoService;
use App\Matricula\MatriculaIncumplida;
use App\Matricula\MatriculaService;
use App\Models\Alumno;
use App\Models\Matricula;
use App\Models\TipoDocumento;
use Carbon\Carbon;
use Database\Seeders\TiposDocumentoSeeder;
use Illuminate\Support\Facades\Log;

/** FR-026: ningún mensaje de error ni el registro técnico llevan nombres ni documentos de alumnos. */
it('los mensajes de error de los servicios no contienen nombres ni documentos', function () {
    $this->seed(TiposDocumentoSeeder::class);
    ['seccion' => $seccion] = escenarioMatricula();
    $alumno = Alumno::factory()->create(['nombres' => 'Secreto Nombre', 'apellidos' => 'Secreto Apellido', 'numero_documento' => '90012345']);
    $matricula = Matricula::factory()->create(['alumno_id' => $alumno->id, 'seccion_id' => $seccion->id]);
    $otro = Alumno::factory()->create();
    $dni = TipoDocumento::where('codigo', 'DNI')->value('id');

    $mensajes = [];
    $intentos = [
        fn () => app(AlumnoService::class)->crear(['nombres' => 'X', 'apellidos' => 'Y', 'tipo_documento_id' => $dni, 'numero_documento' => '90012345', 'fecha_nacimiento' => '2015-01-01', 'sexo' => 'F']),
        fn () => app(AlumnoService::class)->crear(['nombres' => 'X', 'apellidos' => 'Y', 'tipo_documento_id' => $dni, 'numero_documento' => '9001', 'fecha_nacimiento' => '2015-01-01', 'sexo' => 'F']),
        fn () => app(MatriculaService::class)->matricular($alumno, $seccion),
        fn () => app(MatriculaService::class)->matricularVarios($seccion, [$otro->id, $alumno->id]),
        fn () => app(MatriculaService::class)->retirar($matricula, Carbon::parse('2000-01-01')),
        fn () => app(AlumnoService::class)->desactivar($alumno),
        fn () => app(AlumnoService::class)->eliminar($alumno),
    ];

    foreach ($intentos as $intento) {
        try {
            $intento();
        } catch (MatriculaIncumplida $e) {
            $mensajes[] = $e->getMessage();
        }
    }

    expect($mensajes)->toHaveCount(count($intentos));
    foreach ($mensajes as $mensaje) {
        expect($mensaje)->not->toContain('Secreto')->not->toContain('90012345');
    }
});

it('el registro técnico no recibe datos de alumnos al operar con casos inválidos', function () {
    $archivo = tempnam(sys_get_temp_dir(), 'sge-log');
    config(['logging.default' => 'single', 'logging.channels.single.path' => $archivo]);
    Log::forgetChannel('single');

    ['seccion' => $seccion] = escenarioMatricula();
    $alumno = Alumno::factory()->create(['nombres' => 'Secreto Nombre', 'numero_documento' => '90099999']);
    Matricula::factory()->create(['alumno_id' => $alumno->id, 'seccion_id' => $seccion->id]);

    try {
        app(MatriculaService::class)->matricular($alumno, $seccion);
    } catch (MatriculaIncumplida) {
    }

    $contenido = (string) file_get_contents($archivo);
    @unlink($archivo);

    expect($contenido)->not->toContain('Secreto')->not->toContain('90099999');
});

it('la búsqueda de quien no tiene acceso no devuelve datos', function () {
    $alumno = Alumno::factory()->create(['nombres' => 'Secreto Nombre', 'numero_documento' => '90055555']);

    foreach ([usuarioConPermisos([]), usuarioConPermisos(['matricula.ver_propia']), usuarioConPermisos(['matricula.ver_nomina_propia'])] as $sinAcceso) {
        expect(Alumno::query()->visiblePara($sinAcceso)->buscar('Secreto')->count())->toBe(0)
            ->and(Alumno::query()->visiblePara($sinAcceso)->buscar('90055555')->count())->toBe(0);
    }

    expect(Alumno::query()->visiblePara(usuarioConPermisos(['matricula.ver_todas']))->buscar('Secreto')->count())->toBe(1);
});

it('una pantalla denegada no revela datos del alumno en la respuesta', function () {
    $alumno = Alumno::factory()->create(['nombres' => 'Secreto Nombre', 'numero_documento' => '90077777']);

    $respuesta = $this->actingAs(usuarioConPermisos(['matricula.ver_propia']))->get(route('matricula.alumnos.show', $alumno));

    $respuesta->assertForbidden();
    expect($respuesta->getContent())->not->toContain('Secreto')->not->toContain('90077777');
});
