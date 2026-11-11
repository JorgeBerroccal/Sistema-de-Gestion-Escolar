<?php

namespace App\Autorizacion\Pertenencia\Reglas;

use App\Autorizacion\Pertenencia\ReglaPertenencia;
use App\Models\CriterioEvaluacion;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * `notas.ver_todas` ve todo. Si no, se unen (OR) los criterios de los pares (sección, curso) donde el usuario tiene
 * una asignación (`notas.registrar_propia`) y los de secciones donde un alumno propio o hijo tiene matrícula y cuyo
 * periodo está cerrado, es decir publicado (`notas.ver_propia`). Sin ninguno: nada (fail-closed).
 */
class CriterioRegla implements ReglaPertenencia
{
    public function aplicar(Builder $query, Usuario $usuario): Builder
    {
        if ($usuario->tienePermiso('notas.ver_todas')) {
            return $query;
        }

        $docente = $usuario->tienePermiso('notas.registrar_propia');
        $propia = $usuario->tienePermiso('notas.ver_propia');

        if (! $docente && ! $propia) {
            return $query->whereRaw('1 = 0');
        }

        $modelo = $query->getModel();

        return $query->where(function (Builder $q) use ($usuario, $docente, $propia, $modelo) {
            $q->whereRaw('1 = 0');

            if ($docente) {
                $q->orWhereExists(self::asignacionDelPar($usuario, $modelo->qualifyColumn('seccion_id'), $modelo->qualifyColumn('curso_id')));
            }

            if ($propia) {
                $q->orWhere(function (Builder $p) use ($usuario, $modelo) {
                    $p->whereExists(self::periodoPublicado($modelo->qualifyColumn('seccion_id'), $modelo->qualifyColumn('periodo_id')))
                        ->whereExists(self::matriculaPropia($usuario, $modelo->qualifyColumn('seccion_id')));
                });
            }
        });
    }

    public function permite(Usuario $usuario, Model $registro): bool
    {
        if ($usuario->tienePermiso('notas.ver_todas')) {
            return true;
        }

        return $registro instanceof CriterioEvaluacion
            && CriterioEvaluacion::query()->whereKey($registro->getKey())->visiblePara($usuario)->exists();
    }

    /** Subconsulta: el usuario tiene asignación en el par (sección, curso) de las columnas dadas. */
    public static function asignacionDelPar(Usuario $usuario, string $columnaSeccion, string $columnaCurso): \Closure
    {
        return fn (QueryBuilder $s) => $s->selectRaw('1')->from('asignaciones_docentes')
            ->whereColumn('asignaciones_docentes.seccion_id', $columnaSeccion)
            ->whereColumn('asignaciones_docentes.curso_id', $columnaCurso)
            ->where('asignaciones_docentes.usuario_id', $usuario->id);
    }

    /** Subconsulta: la sección tiene el periodo cerrado, o sea publicado. */
    public static function periodoPublicado(string $columnaSeccion, string $columnaPeriodo): \Closure
    {
        return fn (QueryBuilder $s) => $s->selectRaw('1')->from('cierres_periodo')
            ->whereColumn('cierres_periodo.seccion_id', $columnaSeccion)
            ->whereColumn('cierres_periodo.periodo_id', $columnaPeriodo);
    }

    /** Subconsulta: un alumno propio o hijo tiene (o tuvo) matrícula en la sección. */
    public static function matriculaPropia(Usuario $usuario, string $columnaSeccion): \Closure
    {
        return fn (QueryBuilder $s) => $s->selectRaw('1')->from('matriculas')
            ->whereColumn('matriculas.seccion_id', $columnaSeccion)
            ->whereIn('matriculas.alumno_id', MatriculaRegla::alumnosPropios($usuario));
    }
}
