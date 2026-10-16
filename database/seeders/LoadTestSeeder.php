<?php

namespace Database\Seeders;

use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Datos para la prueba de carga (SC-006): 1000 usuarios carga0001…carga1000 con el rol `Carga`
 * (solo `usuarios.ver`). Idempotente. Solo se permite en local, staging y testing (FR-020 de la
 * spec 002); cualquier otro entorno, incluido producción, lo rechaza. Fuera de DatabaseSeeder.
 */
class LoadTestSeeder extends Seeder
{
    public const TOTAL = 1000;

    /** Entornos donde es seguro sembrar 1000 cuentas con contraseña conocida. */
    public const ENTORNOS_PERMITIDOS = ['local', 'staging', 'testing'];

    public function run(): void
    {
        if (! app()->environment(self::ENTORNOS_PERMITIDOS)) {
            throw new RuntimeException(sprintf(
                'LoadTestSeeder solo puede ejecutarse en %s; el entorno actual es "%s".',
                implode(', ', self::ENTORNOS_PERMITIDOS),
                app()->environment(),
            ));
        }

        $this->call(RolesYPermisosSeeder::class);

        $rol = Rol::firstOrCreate(['nombre' => 'Carga'], ['descripcion' => 'Rol de la prueba de carga.']);
        $rol->permisos()->sync([Permiso::where('identificador', 'usuarios.ver')->value('id')]);

        $hash = Hash::make('password'); // un solo hash bcrypt reutilizado
        $ahora = now();

        $filas = [];
        for ($i = 1; $i <= self::TOTAL; $i++) {
            $filas[] = [
                'nombres' => 'Carga',
                'apellidos' => sprintf('Usuario %04d', $i),
                'username' => sprintf('carga%04d', $i),
                'email' => null,
                'password' => $hash,
                'rol_id' => $rol->id,
                'activo' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }

        DB::transaction(function () use ($filas) {
            foreach (array_chunk($filas, 250) as $lote) {
                DB::table('usuarios')->upsert($lote, ['username'], ['password', 'rol_id', 'activo', 'updated_at']);
            }
        });
    }
}
