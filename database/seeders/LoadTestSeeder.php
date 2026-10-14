<?php

namespace Database\Seeders;

use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Datos para la prueba de carga (SC-006): 1000 usuarios carga0001…carga1000 con el rol `Carga`
 * (solo `usuarios.ver`). Idempotente. Prohibido en producción y fuera de DatabaseSeeder.
 */
class LoadTestSeeder extends Seeder
{
    public const TOTAL = 1000;

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('LoadTestSeeder no puede ejecutarse en producción.');
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
