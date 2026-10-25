<?php

namespace App\Academico;

use App\Models\AnioEscolar;
use App\Models\AsignacionDocente;
use App\Models\Curso;
use App\Models\Seccion;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Asignación de docentes a cursos por sección (research R-08). Cada operación abre una transacción que
 * bloquea el año vigente y la sección, para que no compita con `AnioEscolarService::marcarVigente()`,
 * y el índice único `(seccion_id, curso_id)` es la última defensa.
 */
class AsignacionService
{
    /** Usuarios activos cuyo rol tiene el permiso marcador de docente (R-05). */
    public function docentesElegibles(?string $busqueda = null): Builder
    {
        $prefijo = trim((string) $busqueda);

        return Usuario::query()
            ->where('activo', true)
            ->whereHas('rol.permisos', fn ($q) => $q->where('identificador', AsignacionDocente::PERMISO_DOCENTE))
            ->when($prefijo !== '', function ($q) use ($prefijo) {
                $patron = addcslashes($prefijo, '\\%_').'%';
                $q->where(fn ($w) => $w->where('nombres', 'like', $patron)->orWhere('apellidos', 'like', $patron)->orWhere('username', 'like', $patron));
            })
            ->orderBy('apellidos')->orderBy('nombres');
    }

    public function asignar(Seccion $seccion, Curso $curso, Usuario $docente): AsignacionDocente
    {
        return $this->asignarVarios($seccion, [$curso->id], $docente)->first();
    }

    /**
     * Todo o nada: si un curso no se puede asignar no se crea ninguno y el error nombra el curso.
     *
     * @param  array<int, int|string>  $cursoIds
     * @return Collection<int, AsignacionDocente>
     */
    public function asignarVarios(Seccion $seccion, array $cursoIds, Usuario $docente): Collection
    {
        if ($cursoIds === []) {
            throw new ReglaAcademicaIncumplida('Elige al menos un curso para asignar.');
        }

        return DB::transaction(function () use ($seccion, $cursoIds, $docente) {
            $seccion = $this->bloquearSeccionVigente($seccion);
            $this->exigirDocenteElegible($docente);
            $gradoCursos = $seccion->grado->cursos()->pluck('cursos.id')->all();

            return collect($cursoIds)->unique()->map(function ($cursoId) use ($seccion, $docente, $gradoCursos) {
                $curso = Curso::find($cursoId) ?? throw new ReglaAcademicaIncumplida('Uno de los cursos ya no existe.', (int) $cursoId);

                if (! $curso->activo) {
                    throw new ReglaAcademicaIncumplida("El curso {$curso->nombre} está desactivado.", $curso->id);
                }

                if (! in_array($curso->id, $gradoCursos, true)) {
                    throw new ReglaAcademicaIncumplida("El curso {$curso->nombre} no se dicta en el grado de esta sección.", $curso->id);
                }

                if (AsignacionDocente::query()->where('seccion_id', $seccion->id)->where('curso_id', $curso->id)->exists()) {
                    throw new ReglaAcademicaIncumplida("El curso {$curso->nombre} ya tiene docente en esta sección.", $curso->id);
                }

                try {
                    return AsignacionDocente::create(['seccion_id' => $seccion->id, 'curso_id' => $curso->id, 'usuario_id' => $docente->id]);
                } catch (UniqueConstraintViolationException) {
                    throw new ReglaAcademicaIncumplida("El curso {$curso->nombre} ya tiene docente en esta sección.", $curso->id);
                }
            })->values();
        }, 3);
    }

    /** Cambia solo el docente; solo sobre asignaciones del año vigente, para no reescribir el historial. */
    public function reasignar(AsignacionDocente $asignacion, Usuario $docente): void
    {
        DB::transaction(function () use ($asignacion, $docente) {
            $this->bloquearSeccionVigente($asignacion->seccion);
            $this->exigirDocenteElegible($docente);

            AsignacionDocente::query()->whereKey($asignacion->id)->update(['usuario_id' => $docente->id]);
        }, 3);

        $asignacion->refresh();
    }

    /**
     * Quita una asignación del año vigente.
     *
     * Los Módulos 4 (asistencia) y 5 (notas) deberán rechazar aquí las asignaciones que ya tengan registros.
     */
    public function quitar(AsignacionDocente $asignacion): void
    {
        DB::transaction(function () use ($asignacion) {
            $this->bloquearSeccionVigente($asignacion->seccion);

            AsignacionDocente::query()->whereKey($asignacion->id)->delete();
        }, 3);
    }

    /** Bloquea el año vigente y la sección y comprueba que la sección sea de ese año y esté activa. */
    private function bloquearSeccionVigente(Seccion $seccion): Seccion
    {
        $vigente = AnioEscolar::query()->where('vigente', 1)->lockForUpdate()->first()
            ?? throw new ReglaAcademicaIncumplida('No hay un año escolar vigente; márcalo antes de asignar docentes.');

        $bloqueada = Seccion::query()->with('grado')->whereKey($seccion->id)->lockForUpdate()->first()
            ?? throw new ReglaAcademicaIncumplida('La sección ya no existe.');

        if ($bloqueada->anio_escolar_id !== $vigente->id) {
            throw new ReglaAcademicaIncumplida('Solo se pueden modificar asignaciones de secciones del año escolar vigente.');
        }

        if (! $vigente->activo || ! $bloqueada->activo || ! $bloqueada->grado->activo) {
            throw new ReglaAcademicaIncumplida('La sección está desactivada (o lo está su grado).');
        }

        return $bloqueada;
    }

    private function exigirDocenteElegible(Usuario $docente): void
    {
        if (! $this->docentesElegibles()->whereKey($docente->id)->exists()) {
            throw new ReglaAcademicaIncumplida('El usuario elegido no puede ser docente: debe estar activo y su rol debe permitir dictar clases.');
        }
    }
}
