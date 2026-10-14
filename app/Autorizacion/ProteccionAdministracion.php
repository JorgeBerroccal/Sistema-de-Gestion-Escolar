<?php

namespace App\Autorizacion;

use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

/**
 * Evita dejar el sistema sin cuentas administradoras (research R-08, FR-022).
 *
 * Una cuenta administradora es un usuario activo cuyo rol tiene TODOS los permisos de
 * PERMISOS_ADMINISTRACION. Se define por permisos, nunca por nombre de rol (Principio III).
 */
class ProteccionAdministracion
{
    public const PERMISOS_ADMINISTRACION = ['usuarios.editar', 'roles.asignar_permisos'];

    /**
     * Ejecuta una operación que puede quitar administración dentro de una transacción (hasta 3
     * intentos ante deadlock) que antes bloquea con `FOR UPDATE` las cuentas administradoras
     * activas, ordenadas por id. Sin el bloqueo, dos operaciones simultáneas podrían pasar la
     * comprobación a la vez y dejar cero administradores (research R-08).
     *
     * @template T
     *
     * @param  callable(): T  $operacion  debe comprobar con puede*() y hacer el cambio
     * @return T
     */
    public function protegido(callable $operacion): mixed
    {
        return DB::transaction(function () use ($operacion) {
            $this->bloquearCuentasAdministradoras();

            return $operacion();
        }, 3);
    }

    private function bloquearCuentasAdministradoras(): void
    {
        $roles = $this->idsRolesAdministradores();

        if ($roles === []) {
            return;
        }

        Usuario::query()
            ->where('activo', true)
            ->whereIn('rol_id', $roles)
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('id');
    }

    /** @return array<int, int> */
    private function idsRolesAdministradores(): array
    {
        return Rol::query()
            ->whereHas(
                'permisos',
                fn ($q) => $q->whereIn('identificador', self::PERMISOS_ADMINISTRACION),
                '=',
                count(self::PERMISOS_ADMINISTRACION),
            )
            ->pluck('id')
            ->all();
    }

    /**
     * @param  array<int, array<int, string>>|null  $simularPermisosRol  rol_id => identificadores que tendría ese rol
     */
    public function contarCuentasAdministradoras(?int $excluirUsuarioId = null, ?array $simularPermisosRol = null): int
    {
        $rolesAdministradores = $this->idsRolesAdministradores();

        foreach ($simularPermisosRol ?? [] as $rolId => $identificadores) {
            $rolesAdministradores = array_diff($rolesAdministradores, [$rolId]);

            if (! array_diff(self::PERMISOS_ADMINISTRACION, $identificadores)) {
                $rolesAdministradores[] = $rolId;
            }
        }

        if ($rolesAdministradores === []) {
            return 0;
        }

        return Usuario::query()
            ->where('activo', true)
            ->whereIn('rol_id', $rolesAdministradores)
            ->when($excluirUsuarioId, fn ($q) => $q->where('id', '!=', $excluirUsuarioId))
            ->count();
    }

    public function puedeDesactivar(Usuario $objetivo): bool
    {
        if (! $this->esCuentaAdministradora($objetivo)) {
            return true;
        }

        return $this->contarCuentasAdministradoras($objetivo->id) > 0;
    }

    public function puedeCambiarRol(Usuario $objetivo, Rol $nuevoRol): bool
    {
        if (! $this->esCuentaAdministradora($objetivo)) {
            return true;
        }

        if ($this->rolEsAdministrador($nuevoRol)) {
            return true;
        }

        return $this->contarCuentasAdministradoras($objetivo->id) > 0;
    }

    /**
     * @param  array<int, int>  $permisoIds  ids de permisos que tendría el rol tras sincronizar
     */
    public function puedeSincronizarPermisos(Rol $rol, array $permisoIds): bool
    {
        if ($this->contarCuentasAdministradoras() === 0) {
            return true;
        }

        $identificadores = Permiso::whereIn('id', $permisoIds)->pluck('identificador')->all();

        return $this->contarCuentasAdministradoras(null, [$rol->id => $identificadores]) > 0;
    }

    private function esCuentaAdministradora(Usuario $usuario): bool
    {
        return $usuario->activo && $this->rolEsAdministrador($usuario->rol);
    }

    private function rolEsAdministrador(Rol $rol): bool
    {
        return $rol->permisos()->whereIn('identificador', self::PERMISOS_ADMINISTRACION)->count()
            === count(self::PERMISOS_ADMINISTRACION);
    }
}
