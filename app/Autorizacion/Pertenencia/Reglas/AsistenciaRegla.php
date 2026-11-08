<?php

namespace App\Autorizacion\Pertenencia\Reglas;

use App\Autorizacion\Pertenencia\ReglaPertenencia;
use App\Models\Asistencia;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * `asistencia.ver_todas` ve todo. Si no, se unen (OR) los registros de las secciones donde el usuario tiene una
 * asignación (`registrar_propia`) y los de los alumnos propios o hijos (`ver_propia`). Sin ninguno: nada (fail-closed).
 */
class AsistenciaRegla implements ReglaPertenencia
{
    public function aplicar(Builder $query, Usuario $usuario): Builder
    {
        if ($usuario->tienePermiso('asistencia.ver_todas')) {
            return $query;
        }

        $docente = $usuario->tienePermiso('asistencia.registrar_propia');
        $propia = $usuario->tienePermiso('asistencia.ver_propia');

        if (! $docente && ! $propia) {
            return $query->whereRaw('1 = 0');
        }

        $modelo = $query->getModel();

        return $query->where(function (Builder $q) use ($usuario, $docente, $propia, $modelo) {
            $q->whereRaw('1 = 0');

            if ($docente) {
                $q->orWhereIn($modelo->qualifyColumn('seccion_id'), MatriculaRegla::seccionesDelDocente($usuario));
            }

            if ($propia) {
                $q->orWhereIn($modelo->qualifyColumn('alumno_id'), MatriculaRegla::alumnosPropios($usuario));
            }
        });
    }

    public function permite(Usuario $usuario, Model $registro): bool
    {
        if ($usuario->tienePermiso('asistencia.ver_todas')) {
            return true;
        }

        return $registro instanceof Asistencia
            && Asistencia::query()->whereKey($registro->getKey())->visiblePara($usuario)->exists();
    }
}
