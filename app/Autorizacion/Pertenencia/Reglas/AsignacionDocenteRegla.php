<?php

namespace App\Autorizacion\Pertenencia\Reglas;

use App\Autorizacion\Pertenencia\ReglaPertenencia;
use App\Models\AsignacionDocente;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Quien tiene `asignaciones.ver_todas` ve todo; con `asignaciones.ver_propias`, solo sus asignaciones. */
class AsignacionDocenteRegla implements ReglaPertenencia
{
    public function aplicar(Builder $query, Usuario $usuario): Builder
    {
        if ($usuario->tienePermiso('asignaciones.ver_todas')) {
            return $query;
        }

        if ($usuario->tienePermiso('asignaciones.ver_propias')) {
            return $query->where($query->getModel()->qualifyColumn('usuario_id'), $usuario->id);
        }

        return $query->whereRaw('1 = 0');
    }

    public function permite(Usuario $usuario, Model $registro): bool
    {
        if ($usuario->tienePermiso('asignaciones.ver_todas')) {
            return true;
        }

        return $usuario->tienePermiso('asignaciones.ver_propias')
            && $registro instanceof AsignacionDocente
            && $registro->usuario_id === $usuario->id;
    }
}
