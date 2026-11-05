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
        'academico.ver' => ['Ver institución, niveles, años, periodos, grados, secciones y cursos', ['Administrador', 'Director']],
        'academico.gestionar' => ['Gestionar institución, niveles, años, periodos, grados, secciones y cursos', ['Administrador']],
        'asignaciones.ver_todas' => ['Ver todas las asignaciones docentes', ['Administrador', 'Director']],
        'asignaciones.ver_propias' => ['Ver las asignaciones propias', ['Docente']],
        'asignaciones.gestionar' => ['Crear, reasignar y quitar asignaciones docentes', ['Administrador']],
        'docencia.dictar' => ['Dictar clases: puede recibir asignaciones como docente', ['Docente']],
        'matricula.ver_todas' => ['Ver todos los alumnos, nóminas y matrículas', ['Administrador', 'Director']],
        'matricula.gestionar' => ['Gestionar alumnos, vínculos y matrículas', ['Administrador']],
        'matricula.ver_nomina_propia' => ['Ver las nóminas de las secciones propias', ['Docente']],
        'matricula.ver_propia' => ['Ver los alumnos y matrículas propios o de los hijos', ['Padre de Familia', 'Alumno']],
        'matricula.ser_alumno' => ['Puede vincularse a una ficha de alumno', ['Alumno']],
        'matricula.ser_apoderado' => ['Puede vincularse como apoderado de alumnos', ['Padre de Familia']],
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
