<?php

namespace Database\Factories;

use App\Models\AsignacionDocente;
use App\Models\Curso;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Seccion;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AsignacionDocente> */
class AsignacionDocenteFactory extends Factory
{
    protected $model = AsignacionDocente::class;

    public function definition(): array
    {
        return [
            'seccion_id' => Seccion::factory(),
            'curso_id' => Curso::factory(),
            'usuario_id' => fn () => self::docenteElegible()->id,
        ];
    }

    /** Usuario activo cuyo rol tiene el permiso marcador de docente. */
    public static function docenteElegible(): Usuario
    {
        $permiso = Permiso::firstOrCreate(
            ['identificador' => AsignacionDocente::PERMISO_DOCENTE],
            ['nombre' => 'Puede recibir asignaciones como docente'],
        );
        $rol = Rol::factory()->create();
        $rol->permisos()->attach($permiso);

        return Usuario::factory()->create(['rol_id' => $rol->id, 'activo' => true]);
    }
}
