<?php

namespace App\Matricula;

use App\Models\Alumno;
use App\Models\AnioEscolar;
use App\Models\Matricula;
use App\Models\Seccion;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Matrícula de alumnos en secciones del año vigente (research R-01, R-02, R-07, R-08). Cada operación abre una
 * transacción que bloquea el año vigente y al alumno, para no competir con el cambio de año vigente ni con otra
 * matrícula del mismo alumno; el índice único `(alumno_id, anio_activa_id)` es la última defensa. Los mensajes
 * nunca llevan nombres ni documentos: la masiva identifica al alumno por su posición en la lista (R-11).
 */
class MatriculaService
{
    public function matricular(Alumno $alumno, Seccion $seccion, ?CarbonInterface $fecha = null): Matricula
    {
        return DB::transaction(fn () => $this->matricularBloqueado($alumno->id, $seccion->id, $fecha), 3);
    }

    /**
     * Todo o nada: si un alumno no se puede matricular no se matricula ninguno y el error dice su posición.
     *
     * @param  array<int, int|string>  $alumnoIds
     * @return Collection<int, Matricula>
     */
    public function matricularVarios(Seccion $seccion, array $alumnoIds, ?CarbonInterface $fecha = null): Collection
    {
        $alumnoIds = array_values(array_unique($alumnoIds));

        if ($alumnoIds === []) {
            throw new MatriculaIncumplida('Elige al menos un alumno para matricular.');
        }

        return DB::transaction(function () use ($seccion, $alumnoIds, $fecha) {
            return collect($alumnoIds)->map(function ($alumnoId, int $indice) use ($seccion, $fecha) {
                try {
                    return $this->matricularBloqueado((int) $alumnoId, $seccion->id, $fecha);
                } catch (MatriculaIncumplida $e) {
                    $posicion = $indice + 1;

                    throw new MatriculaIncumplida("No se matriculó a ninguno. Alumno {$posicion} de la lista: {$e->getMessage()}", $posicion);
                }
            })->values();
        }, 3);
    }

    /** Retira al alumno: deja de figurar en la nómina activa y conserva la matrícula en su historial. */
    public function retirar(Matricula $matricula, CarbonInterface $fecha, ?string $motivo = null): Matricula
    {
        return $this->cambiarEstado($matricula, EstadoMatricula::Retirado, $fecha, $motivo);
    }

    public function trasladar(Matricula $matricula, CarbonInterface $fecha, ?string $motivo = null): Matricula
    {
        return $this->cambiarEstado($matricula, EstadoMatricula::Trasladado, $fecha, $motivo);
    }

    /** Corrige un retiro o traslado hecho por error, si el alumno no tiene otra matrícula activa ese año. */
    public function corregir(Matricula $matricula): Matricula
    {
        return DB::transaction(function () use ($matricula) {
            [$actual] = $this->bloquear($matricula);

            if ($actual->estado->esActiva()) {
                throw new MatriculaIncumplida('La matrícula ya está activa.');
            }

            if ($this->tieneActivaEnElAnio($actual->alumno_id, $actual->anio_id)) {
                throw new MatriculaIncumplida('El alumno ya tiene otra matrícula activa en ese año.');
            }

            try {
                $actual->update(['estado' => EstadoMatricula::Matriculado, 'fecha_estado' => null, 'motivo' => null]);
            } catch (UniqueConstraintViolationException) {
                throw new MatriculaIncumplida('El alumno ya tiene otra matrícula activa en ese año.');
            }

            return $actual;
        }, 3);
    }

    private function cambiarEstado(Matricula $matricula, EstadoMatricula $nuevo, CarbonInterface $fecha, ?string $motivo): Matricula
    {
        $motivo = $motivo === null ? null : preg_replace('/\s+/u', ' ', trim($motivo));

        if (($motivo !== null) && mb_strlen($motivo) > 255) {
            throw new MatriculaIncumplida('El motivo admite como máximo 255 caracteres.');
        }

        return DB::transaction(function () use ($matricula, $nuevo, $fecha, $motivo) {
            [$actual] = $this->bloquear($matricula);

            if (! $actual->estado->esActiva()) {
                throw new MatriculaIncumplida('Solo se puede cambiar el estado de una matrícula activa.');
            }

            if ($fecha->toDateString() < $actual->fecha_matricula->toDateString()) {
                throw new MatriculaIncumplida('La fecha no puede ser anterior a la fecha de matrícula.');
            }

            $actual->update(['estado' => $nuevo, 'fecha_estado' => $fecha->toDateString(), 'motivo' => ($motivo === '' ? null : $motivo)]);

            return $actual;
        }, 3);
    }

    /** @return array{0: Matricula, 1: Alumno} la matrícula y su alumno, bloqueados */
    private function bloquear(Matricula $matricula): array
    {
        $alumno = Alumno::query()->whereKey($matricula->alumno_id)->lockForUpdate()->firstOrFail();
        $actual = Matricula::query()->whereKey($matricula->id)->lockForUpdate()->firstOrFail();

        return [$actual, $alumno];
    }

    private function matricularBloqueado(int $alumnoId, int $seccionId, ?CarbonInterface $fecha): Matricula
    {
        $vigente = AnioEscolar::query()->where('vigente', 1)->lockForUpdate()->first()
            ?? throw new MatriculaIncumplida('No hay un año escolar vigente; márcalo antes de matricular.');

        $alumno = Alumno::query()->whereKey($alumnoId)->lockForUpdate()->first()
            ?? throw new MatriculaIncumplida('El alumno ya no existe.');
        $seccion = Seccion::query()->whereKey($seccionId)->lockForUpdate()->first()
            ?? throw new MatriculaIncumplida('La sección ya no existe.');

        if (! $alumno->activo) {
            throw new MatriculaIncumplida('El alumno está desactivado.');
        }

        if (! $seccion->activo) {
            throw new MatriculaIncumplida('La sección está desactivada.');
        }

        if ($seccion->anio_escolar_id !== $vigente->id) {
            throw new MatriculaIncumplida('Solo se puede matricular en secciones del año escolar vigente.');
        }

        if ($this->tieneActivaEnElAnio($alumno->id, $vigente->id)) {
            throw new MatriculaIncumplida('El alumno ya tiene una matrícula activa en este año.');
        }

        try {
            return Matricula::create([
                'alumno_id' => $alumno->id,
                'seccion_id' => $seccion->id,
                'anio_id' => $seccion->anio_escolar_id,
                'fecha_matricula' => ($fecha ?? now())->toDateString(),
                'estado' => EstadoMatricula::Matriculado,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new MatriculaIncumplida('El alumno ya tiene una matrícula activa en este año.');
        }
    }

    private function tieneActivaEnElAnio(int $alumnoId, int $anioId): bool
    {
        return Matricula::query()->where('alumno_id', $alumnoId)->where('anio_id', $anioId)->activas()->exists();
    }
}
