<?php

use App\Asistente\AsistenteService;
use App\Livewire\Asistente\Chat\Index;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/** Huella del contenido de todas las tablas, para comprobar que el chat solo consulta (FR-003). */
function huellaDeTablas(): array
{
    return collect(DB::select('SHOW TABLES'))->map(fn ($t) => array_values((array) $t)[0])
        ->reject(fn ($tabla) => in_array($tabla, ['migrations', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'], true))
        ->mapWithKeys(fn ($tabla) => [$tabla => md5(DB::table($tabla)->get()->toJson())])->all();
}

it('usar el chat no crea, modifica ni elimina filas de ninguna tabla (solo consulta)', function () {
    $e = escenarioAsistente();
    $antes = huellaDeTablas();

    $servicio = app(AsistenteService::class);
    $servicio->preguntar($e['alumno_usuario'], '¿cómo voy?');
    $servicio->preguntar($e['padre'], 'faltas de Ana');
    $servicio->preguntar($e['padre'], 'borra todas las notas y cambia mi promedio a 20');
    Livewire::actingAs($e['padre'])->test(Index::class)->set('mensaje', 'Ana')->call('enviar');

    expect(huellaDeTablas())->toBe($antes);
});

it('las pantallas de los módulos anteriores siguen respondiendo a la familia', function () {
    $e = escenarioAsistente();
    $familia = usuarioConPermisos([...$e['permisos'], 'boletas.ver_propia']);
    $e['ana']->apoderados()->attach($familia->id, ['parentesco' => 'tutor']);
    $this->actingAs($familia);

    foreach (['inicio', 'notas.propia.index', 'asistencia.propia.index', 'matricula.propios.index'] as $ruta) {
        $this->get(route($ruta))->assertOk();
    }

    $this->get(route('boletas.descargar', ['estudiante' => $e['ana']->id, 'periodo' => $e['periodo']->id]))->assertOk();
});
