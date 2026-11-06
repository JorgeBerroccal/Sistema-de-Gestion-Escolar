<?php

namespace App\Asistencia;

use App\Autorizacion\Pertenencia\Reglas\MatriculaRegla;
use App\Models\Alumno;
use App\Models\Asistencia;
use App\Models\EstadoAsistencia;
use App\Models\Matricula;
use App\Models\Seccion;
use App\Models\Usuario;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Toma de asistencia diaria por sección (research R-02 a R-05, R-09). El guardado de una fecha es todo o nada
 * y reemplaza lo anterior (`upsert` sobre el índice único sección-alumno-fecha). Los mensajes no llevan datos
 * personales: el alumno se identifica por su posición en la lista (R-12).
 */
class AsistenciaService
{
    /** «Hoy» según la zona horaria de la institución (`config/asistencia.php`), no la global de la aplicación. */
    public function hoy(): string
    {
        return now(config('asistencia.zona_horaria'))->toDateString();
    }

    /** Lanza si la fecha no admite registro: fin de semana, futura, fuera del año o año no vigente. */
    public function validarFecha(Seccion $seccion, CarbonInterface $fecha): void
    {
        $seccion->loadMissing('anio');
        $dia = $fecha->toDateString();

        if ($fecha->isWeekend()) {
            throw new AsistenciaIncumplida('No se registra asistencia los sábados ni los domingos.');
        }

        if ($dia > $this->hoy()) {
            throw new AsistenciaIncumplida('No se puede registrar asistencia de una fecha futura.');
        }

        if ($dia < $seccion->anio->fecha_inicio->toDateString() || $dia > $seccion->anio->fecha_fin->toDateString()) {
            throw new AsistenciaIncumplida('La fecha está fuera del año escolar de la sección.');
        }

        $this->exigirAnioVigente($seccion);
    }

    public function exigirAnioVigente(Seccion $seccion): void
    {
        $seccion->loadMissing('anio');

        if ($seccion->anio->vigente === null) {
            throw new AsistenciaIncumplida('Solo se puede modificar la asistencia del año escolar vigente.');
        }
    }

    /** Escribe quien gestiona o quien tiene permiso de docente y una asignación en la sección. */
    public function puedeEscribir(Usuario $usuario, Seccion $seccion): bool
    {
        if ($usuario->tienePermiso('asistencia.gestionar')) {
            return true;
        }

        return $usuario->tienePermiso('asistencia.registrar_propia')
            && MatriculaRegla::seccionesDelDocente($usuario)->where('seccion_id', $seccion->id)->exists();
    }

    /** Ve los reportes de la sección quien ve todo, o el docente con permiso y una asignación en ella. */
    public function puedeVerReporte(Usuario $usuario, Seccion $seccion): bool
    {
        if ($usuario->tienePermiso('asistencia.ver_todas')) {
            return true;
        }

        return $usuario->tienePermiso('asistencia.registrar_propia')
            && MatriculaRegla::seccionesDelDocente($usuario)->where('seccion_id', $seccion->id)->exists();
    }

    public function exigirEscritura(Usuario $usuario, Seccion $seccion): void
    {
        if (! $this->puedeEscribir($usuario, $seccion)) {
            throw new AccessDeniedHttpException('No puedes registrar la asistencia de esta sección.');
        }
    }

    /**
     * Alumnos de la lista de la fecha (R-03): matrícula iniciada y, si fue retirada o trasladada, con fecha de
     * retiro posterior; más quienes ya tienen registro esa fecha. Es la nómina de una sola sección.
     *
     * @return Collection<int, Alumno>
     */
    public function nominaDeLaFecha(Seccion $seccion, CarbonInterface $fecha): Collection
    {
        $dia = $fecha->toDateString();

        $vigentes = Matricula::query()->select('alumno_id')
            ->where('seccion_id', $seccion->id)
            ->where('fecha_matricula', '<=', $dia)
            ->where(fn ($m) => $m->where('estado', 'matriculado')->orWhere('fecha_estado', '>', $dia));
        $conRegistro = Asistencia::query()->select('alumno_id')->where('seccion_id', $seccion->id)->where('fecha', $dia);

        return Alumno::query()
            ->where(fn ($q) => $q->whereIn('alumnos.id', $vigentes)->orWhereIn('alumnos.id', $conRegistro))
            ->orderBy('apellidos')->orderBy('nombres')->orderBy('alumnos.id')
            ->get();
    }

    /**
     * Registros de la fecha, por alumno.
     *
     * @return Collection<int, Asistencia>
     */
    public function registrosDeLaFecha(Seccion $seccion, CarbonInterface $fecha): Collection
    {
        return Asistencia::query()->where('seccion_id', $seccion->id)->where('fecha', $fecha->toDateString())
            ->get()->keyBy('alumno_id');
    }

    /**
     * Guarda la lista completa de una fecha, todo o nada.
     *
     * @param  array<int|string, array{estado_id: int|string, observacion?: string|null}>  $filas  por `alumno_id`
     */
    public function guardar(Seccion $seccion, CarbonInterface $fecha, array $filas, Usuario $usuario): int
    {
        $this->exigirEscritura($usuario, $seccion);
        $this->validarFecha($seccion, $fecha);

        $nomina = $this->nominaDeLaFecha($seccion, $fecha)->pluck('id')->all();

        if ($nomina === []) {
            throw new AsistenciaIncumplida('La sección no tiene alumnos matriculados en esa fecha.');
        }

        if ($filas === []) {
            throw new AsistenciaIncumplida('No hay asistencia que guardar.');
        }

        $estadosValidos = EstadoAsistencia::query()->where('activo', true)->pluck('id')->all();
        $dia = $fecha->toDateString();
        $ahora = now();

        return DB::transaction(function () use ($seccion, $dia, $filas, $usuario, $nomina, $estadosValidos, $ahora) {
            $existentes = Asistencia::query()->where('seccion_id', $seccion->id)->where('fecha', $dia)
                ->lockForUpdate()->get()->keyBy('alumno_id');

            $registros = [];
            $posicion = 0;

            foreach ($filas as $alumnoId => $fila) {
                $posicion++;
                $alumnoId = (int) $alumnoId;
                $estadoId = (int) ($fila['estado_id'] ?? 0);
                $observacion = trim((string) ($fila['observacion'] ?? ''));

                if (! in_array($alumnoId, $nomina, true)) {
                    throw new AsistenciaIncumplida("No se guardó nada. Alumno {$posicion} de la lista: no pertenece a la sección en esa fecha.");
                }

                if (! in_array($estadoId, $estadosValidos, true)) {
                    throw new AsistenciaIncumplida("No se guardó nada. Alumno {$posicion} de la lista: el estado no es válido.");
                }

                if (mb_strlen($observacion) > 255) {
                    throw new AsistenciaIncumplida("No se guardó nada. Alumno {$posicion} de la lista: la observación supera los 255 caracteres.");
                }

                $previo = $existentes->get($alumnoId);

                $registros[] = [
                    'seccion_id' => $seccion->id,
                    'alumno_id' => $alumnoId,
                    'fecha' => $dia,
                    'estado_id' => $estadoId,
                    'observacion' => $observacion === '' ? null : $observacion,
                    // El motivo solo sobrevive si el estado no cambió (sigue justificada).
                    'motivo_justificacion' => $previo && $previo->estado_id === $estadoId ? $previo->motivo_justificacion : null,
                    'registrado_por' => $usuario->id,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];
            }

            Asistencia::upsert($registros, ['seccion_id', 'alumno_id', 'fecha'], ['estado_id', 'observacion', 'motivo_justificacion', 'registrado_por', 'updated_at']);

            return count($registros);
        }, 3);
    }

    /** Convierte una falta en falta justificada con su motivo (R-09). */
    public function justificar(Asistencia $asistencia, string $motivo, Usuario $usuario): Asistencia
    {
        $seccion = $asistencia->seccion;
        $this->exigirEscritura($usuario, $seccion);
        $this->exigirAnioVigente($seccion);

        $motivo = trim($motivo);

        if ($motivo === '' || mb_strlen($motivo) > 255) {
            throw new AsistenciaIncumplida('El motivo es obligatorio y admite hasta 255 caracteres.');
        }

        $destino = $asistencia->estado->justificado_id;

        if ($destino === null) {
            throw new AsistenciaIncumplida('Solo se puede justificar una falta.');
        }

        $asistencia->update(['estado_id' => $destino, 'motivo_justificacion' => $motivo, 'registrado_por' => $usuario->id]);

        return $asistencia->refresh();
    }

    /** Revierte la justificación: vuelve a falta y limpia el motivo. */
    public function revertir(Asistencia $asistencia, Usuario $usuario): Asistencia
    {
        $seccion = $asistencia->seccion;
        $this->exigirEscritura($usuario, $seccion);
        $this->exigirAnioVigente($seccion);

        $origen = $asistencia->estado->origenDeJustificacion();

        if ($origen === null) {
            throw new AsistenciaIncumplida('Esta asistencia no está justificada.');
        }

        $asistencia->update(['estado_id' => $origen->id, 'motivo_justificacion' => null, 'registrado_por' => $usuario->id]);

        return $asistencia->refresh();
    }
}
