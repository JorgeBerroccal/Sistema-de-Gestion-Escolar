<?php

namespace App\Autorizacion\Pertenencia;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Define qué registros de un modelo puede ver un usuario.
 *
 * Restricciones para quien implemente una regla:
 * - MUST decidir según el usuario y sus relaciones con los datos; MUST NOT comparar el nombre del rol.
 * - Si la visibilidad varía según la capacidad del usuario (p. ej. un Director ve todo), MUST
 *   expresarse con permisos (`$usuario->tienePermiso('matriculas.ver_todas')`), no con roles.
 */
interface ReglaPertenencia
{
    /** Restringe una consulta a los registros visibles para el usuario. */
    public function aplicar(Builder $query, Usuario $usuario): Builder;

    /** Indica si el usuario puede acceder a un registro concreto. */
    public function permite(Usuario $usuario, Model $registro): bool;
}
