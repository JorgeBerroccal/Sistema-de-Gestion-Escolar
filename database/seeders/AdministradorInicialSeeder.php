<?php

namespace Database\Seeders;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use RuntimeException;

class AdministradorInicialSeeder extends Seeder
{
    public function run(): void
    {
        $config = config('sge.admin');
        $password = (string) ($config['password'] ?? '');
        $username = mb_strtolower(trim((string) ($config['username'] ?? '')));

        if ($username === '') {
            throw new RuntimeException('Define SGE_ADMIN_USERNAME en .env antes de ejecutar los seeders.');
        }

        if ($password === '') {
            throw new RuntimeException('Define SGE_ADMIN_PASSWORD en .env antes de ejecutar los seeders.');
        }

        if (mb_strlen($password) < 8) {
            throw new RuntimeException('SGE_ADMIN_PASSWORD debe tener al menos 8 caracteres.');
        }

        $rolId = Rol::where('nombre', 'Administrador')->value('id')
            ?? throw new RuntimeException('No existe el rol Administrador; ejecuta antes RolesYPermisosSeeder.');

        // Aquí sí se permite el nombre de rol: es un seeder, no una decisión de acceso.
        Usuario::firstOrCreate(['username' => $username], [
            'nombres' => 'Administrador',
            'apellidos' => 'del Sistema',
            'email' => $config['email'],
            'password' => $password,
            'rol_id' => $rolId,
            'activo' => true,
        ]);
    }
}
