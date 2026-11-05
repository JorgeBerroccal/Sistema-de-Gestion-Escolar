<?php

namespace App\Autorizacion\Pertenencia\Reglas;

use App\Autorizacion\Pertenencia\ReglaPertenencia;
use App\Models\Alumno;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Quien tiene `matricula.ver_todas` ve todos los alumnos; con `matricula.ver_propia`, solo la ficha vinculada a
 * su usuario y la de los alumnos de los que es apoderado. La nómina del Docente no pasa por aquí: no ve fichas.
 */
class AlumnoRegla implements ReglaPertenencia
{
    public function aplicar(Builder $query, Usuario $usuario): Builder
    {
        if ($usuario->tienePermiso('matricula.ver_todas')) {
            return $query;
        }

        if ($usuario->tienePermiso('matricula.ver_propia')) {
            $alumnos = $query->getModel();

            return $query->where(fn (Builder $q) => $q
                ->where($alumnos->qualifyColumn('usuario_id'), $usuario->id)
                ->orWhereIn($alumnos->qualifyColumn('id'), self::idsComoApoderado($usuario)));
        }

        return $query->whereRaw('1 = 0');
    }

    public function permite(Usuario $usuario, Model $registro): bool
    {
        if ($usuario->tienePermiso('matricula.ver_todas')) {
            return true;
        }

        if (! $usuario->tienePermiso('matricula.ver_propia') || ! $registro instanceof Alumno) {
            return false;
        }

        return $registro->usuario_id === $usuario->id
            || $registro->apoderados()->where('usuarios.id', $usuario->id)->exists();
    }

    /** Subconsulta con los alumnos de los que el usuario es apoderado. */
    public static function idsComoApoderado(Usuario $usuario): \Illuminate\Database\Query\Builder
    {
        return DB::table('alumno_apoderado')->select('alumno_id')->where('usuario_id', $usuario->id);
    }
}
