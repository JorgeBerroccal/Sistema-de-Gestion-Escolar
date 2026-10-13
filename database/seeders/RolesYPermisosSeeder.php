<?php

namespace Database\Seeders;

use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Database\Seeder;

/**
 * Roles y permisos base (research R-07). Idempotente: un permiso solo se asigna a sus roles la
 * primera vez que se crea, para no deshacer cambios hechos después por el Administrador.
 */
class RolesYPermisosSeeder extends Seeder
{
    public const ROLES = [
        'Administrador' => 'Gestiona usuarios, roles y permisos del sistema.',
        'Director' => 'Consulta usuarios y roles.',
        'Docente' => 'Personal docente.',
        'Padre de Familia' => 'Padre, madre o tutor de un alumno.',
        'Alumno' => 'Estudiante.',
    ];

    /** identificador => [nombre visible, roles que lo reciben] */
    public const PERMISOS = [
        'usuarios.ver' => ['Ver usuarios', ['Administrador', 'Director']],
        'usuarios.crear' => ['Crear usuarios', ['Administrador']],
        'usuarios.editar' => ['Editar usuarios, cambiar rol y restablecer contraseña', ['Administrador']],
        'usuarios.desactivar' => ['Desactivar y reactivar usuarios', ['Administrador']],
        'roles.ver' => ['Ver roles y permisos', ['Administrador', 'Director']],
        'permisos.gestionar' => ['Crear y editar permisos', ['Administrador']],
        'roles.asignar_permisos' => ['Asignar permisos a roles', ['Administrador']],
    ];

    public function run(): void
    {
        $roles = [];
        foreach (self::ROLES as $nombre => $descripcion) {
            $roles[$nombre] = Rol::firstOrCreate(['nombre' => $nombre], ['descripcion' => $descripcion]);
        }

        foreach (self::PERMISOS as $identificador => [$nombre, $nombresRoles]) {
            $permiso = Permiso::firstOrCreate(['identificador' => $identificador], ['nombre' => $nombre]);

            if ($permiso->wasRecentlyCreated) {
                $permiso->roles()->attach(array_map(fn (string $r) => $roles[$r]->id, $nombresRoles));
            }
        }
    }
}
