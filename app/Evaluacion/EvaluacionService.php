<?php

namespace App\Evaluacion;

use App\Models\Alumno;
use App\Models\AsignacionDocente;
use App\Models\CierrePeriodo;
use App\Models\Curso;
use App\Models\EscalaCalificacion;
use App\Models\Institucion;
use App\Models\Matricula;
use App\Models\PeriodoEvaluacion;
use App\Models\Seccion;
use App\Models\Usuario;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Reglas de escritura y datos base de la evaluación (research R-05, R-08, R-09). Los mensajes no llevan datos
 * personales: se identifica por posición (fila/columna) o #id (R-17).
 */
class EvaluacionService
{
    /** Escala de calificación configurada en la institución. */
    public function escala(): EscalaCalificacion
    {
        return Institucion::actual()->escala
            ?? throw new EvaluacionIncumplida('La institución no tiene una escala de calificación configurada.');
    }

    public function calculo(): CalculoPromedios
    {
        return CalculoPromedios::deEscala($this->escala());
    }

    public function exigirAnioVigente(Seccion $seccion): void
    {
        $seccion->loadMissing('anio');

        if ($seccion->anio->vigente === null) {
            throw new EvaluacionIncumplida('Solo se pueden modificar las notas del año escolar vigente.');
        }
    }

    /** El periodo debe pertenecer al año de la sección. */
    public function exigirPeriodoDelAnio(Seccion $seccion, PeriodoEvaluacion $periodo): void
    {
        if ($periodo->anio_escolar_id !== $seccion->anio_escolar_id) {
            throw new EvaluacionIncumplida('El periodo no pertenece al año escolar de la sección.');
        }
    }

    /** Fila presente en `cierres_periodo` = cerrado y publicado (R-05). */
    public function periodoCerrado(Seccion $seccion, PeriodoEvaluacion $periodo): bool
    {
        return CierrePeriodo::query()->where('seccion_id', $seccion->id)->where('periodo_id', $periodo->id)->exists();
    }

    /**
     * Escribe quien gestiona, o quien tiene `notas.registrar_propia`, conserva el marcador de docente y tiene una
     * asignación en el par (sección, curso) (R-08, R-06 del Módulo 2).
     */
    public function puedeEscribir(Usuario $usuario, Seccion $seccion, Curso $curso): bool
    {
        if ($usuario->tienePermiso('notas.gestionar')) {
            return true;
        }

        return $usuario->tienePermiso('notas.registrar_propia')
            && $usuario->tienePermiso(AsignacionDocente::PERMISO_DOCENTE)
            && AsignacionDocente::query()->where('usuario_id', $usuario->id)
                ->where('seccion_id', $seccion->id)->where('curso_id', $curso->id)->exists();
    }

    /** Abre la pantalla de notas quien puede escribir o ver todo, o el docente con asignación en el par. */
    public function puedeAbrir(Usuario $usuario, Seccion $seccion, Curso $curso): bool
    {
        return $usuario->tienePermiso('notas.ver_todas')
            || $this->puedeEscribir($usuario, $seccion, $curso)
            || ($usuario->tienePermiso('notas.registrar_propia')
                && AsignacionDocente::query()->where('usuario_id', $usuario->id)
                    ->where('seccion_id', $seccion->id)->where('curso_id', $curso->id)->exists());
    }

    /**
     * Comprueba todo lo necesario para escribir: permiso y asignación (403), año vigente, periodo del año y, para
     * quien no gestiona, periodo abierto.
     */
    public function exigirEscritura(Usuario $usuario, Seccion $seccion, Curso $curso, PeriodoEvaluacion $periodo): void
    {
        if (! $this->puedeEscribir($usuario, $seccion, $curso)) {
            throw new AccessDeniedHttpException('No puedes registrar las notas de este curso y sección.');
        }

        $this->exigirAnioVigente($seccion);
        $this->exigirPeriodoDelAnio($seccion, $periodo);

        if (! $usuario->tienePermiso('notas.gestionar') && $this->periodoCerrado($seccion, $periodo)) {
            throw new EvaluacionIncumplida('El periodo está cerrado: solo el Administrador puede corregir las notas.');
        }
    }

    /**
     * Alumnos con matrícula activa en la sección, por apellidos, nombres e id (R-09).
     *
     * @return Collection<int, Alumno>
     */
    public function nominaActiva(Seccion $seccion): Collection
    {
        $activos = Matricula::query()->select('alumno_id')->where('seccion_id', $seccion->id)->where('estado', 'matriculado');

        return Alumno::query()->whereIn('alumnos.id', $activos)
            ->orderBy('apellidos')->orderBy('nombres')->orderBy('alumnos.id')->get();
    }
}
