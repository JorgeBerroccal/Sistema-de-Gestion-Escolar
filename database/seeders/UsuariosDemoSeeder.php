<?php

namespace Database\Seeders;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Database\Seeder;

/** Un usuario por rol para probar en local (contraseña `password`). Nunca en producción. */
class UsuariosDemoSeeder extends Seeder
{
    public const USUARIOS = [
        'director.demo' => ['Director', 'Director', 'Demo'],
        'docente.demo' => ['Docente', 'Docente', 'Demo'],
        'padre.demo' => ['Padre de Familia', 'Padre', 'Demo'],
        'alumno.demo' => ['Alumno', 'Alumno', 'Demo'],
    ];

    public function run(): void
    {
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
