<?php

namespace Database\Seeders;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use RuntimeException;

/** Un usuario por rol para probar (contraseña conocida `password`). Solo en local (y en las pruebas): nunca en staging ni producción. */
class UsuariosDemoSeeder extends Seeder
{
    public const USUARIOS = [
        'director.demo' => ['Director', 'Director', 'Demo'],
        'docente.demo' => ['Docente', 'Docente', 'Demo'],
        'padre.demo' => ['Padre de Familia', 'Padre', 'Demo'],
        'alumno.demo' => ['Alumno', 'Alumno', 'Demo'],
    ];

    public const ENTORNOS_PERMITIDOS = ['local', 'testing'];

    public function run(): void
    {
        if (! app()->environment(self::ENTORNOS_PERMITIDOS)) {
            throw new RuntimeException(sprintf(
                'UsuariosDemoSeeder crea cuentas con contraseña conocida y solo puede ejecutarse en %s; el entorno actual es "%s".',
                implode(', ', self::ENTORNOS_PERMITIDOS),
                app()->environment(),
            ));
        }

        foreach (self::USUARIOS as $username => [$rol, $nombres, $apellidos]) {
            Usuario::firstOrCreate(['username' => $username], [
                'nombres' => $nombres,
                'apellidos' => $apellidos,
                'password' => 'password',
                'rol_id' => Rol::where('nombre', $rol)->value('id'),
                'activo' => true,
            ]);
        }
    }
}
