<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([RolesYPermisosSeeder::class, EscalasCalificacionSeeder::class, TiposDocumentoSeeder::class, EstadosAsistenciaSeeder::class, AdministradorInicialSeeder::class]);

        if (app()->environment('local')) {
            $this->call([UsuariosDemoSeeder::class, AcademicoDemoSeeder::class, MatriculaDemoSeeder::class, AsistenciaDemoSeeder::class]);
        }
    }
}
