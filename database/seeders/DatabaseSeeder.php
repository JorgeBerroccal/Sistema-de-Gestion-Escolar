<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([RolesYPermisosSeeder::class, EscalasCalificacionSeeder::class, AdministradorInicialSeeder::class]);

        if (app()->environment('local')) {
            $this->call(UsuariosDemoSeeder::class);
        }
    }
}
