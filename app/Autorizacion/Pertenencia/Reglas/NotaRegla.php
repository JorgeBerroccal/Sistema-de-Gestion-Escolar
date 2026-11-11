<?php

namespace App\Autorizacion\Pertenencia\Reglas;

use App\Autorizacion\Pertenencia\ReglaPertenencia;
use App\Models\Nota;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * `notas.ver_todas` ve todo. Si no, se unen (OR) las notas de criterios de pares (sección, curso) con asignación
 * del usuario (`notas.registrar_propia`) y las notas de alumnos propios o hijos en periodos cerrados, o sea
 * publicados (`notas.ver_propia`). Sin ninguno: nada (fail-closed).
 */
class NotaRegla implements ReglaPertenencia
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
                $q->orWhereExists(fn (QueryBuilder $s) => $s->selectRaw('1')->from('criterios_evaluacion')
                    ->whereColumn('criterios_evaluacion.id', $modelo->qualifyColumn('criterio_id'))
                    ->whereExists(CriterioRegla::asignacionDelPar($usuario, 'criterios_evaluacion.seccion_id', 'criterios_evaluacion.curso_id')));
            }

            if ($propia) {
                $q->orWhere(function (Builder $p) use ($usuario, $modelo) {
                    $p->whereIn($modelo->qualifyColumn('alumno_id'), MatriculaRegla::alumnosPropios($usuario))
                        ->whereExists(fn (QueryBuilder $s) => $s->selectRaw('1')->from('criterios_evaluacion')
                            ->whereColumn('criterios_evaluacion.id', $modelo->qualifyColumn('criterio_id'))
                            ->whereExists(CriterioRegla::periodoPublicado('criterios_evaluacion.seccion_id', 'criterios_evaluacion.periodo_id')));
                });
            }
        });
    }

    public function permite(Usuario $usuario, Model $registro): bool
    {
        if ($usuario->tienePermiso('notas.ver_todas')) {
            return true;
        }

        return $registro instanceof Nota
            && Nota::query()->whereKey($registro->getKey())->visiblePara($usuario)->exists();
    }
}
