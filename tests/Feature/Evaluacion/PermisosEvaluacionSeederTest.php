<?php

use App\Models\Permiso;
use App\Models\Rol;
use Database\Seeders\RolesYPermisosSeeder;

function permisosEvaluacionDe(string $rol): array
{
    return Rol::where('nombre', $rol)->first()->permisos()
        ->where(fn ($q) => $q->where('identificador', 'like', 'notas.%')->orWhere('identificador', 'like', 'boletas.%'))
        ->pluck('identificador')->sort()->values()->all();
}

it('asigna por defecto los permisos de evaluación según el contrato', function () {
    $this->seed(RolesYPermisosSeeder::class);

    expect(permisosEvaluacionDe('Administrador'))->toBe(['boletas.ver_todas', 'notas.cerrar_periodo', 'notas.gestionar', 'notas.ver_todas'])
        ->and(permisosEvaluacionDe('Director'))->toBe(['boletas.ver_todas', 'notas.ver_todas'])
        ->and(permisosEvaluacionDe('Docente'))->toBe(['notas.registrar_propia'])
        ->and(permisosEvaluacionDe('Padre de Familia'))->toBe(['boletas.ver_propia', 'notas.ver_propia'])
        ->and(permisosEvaluacionDe('Alumno'))->toBe(['boletas.ver_propia', 'notas.ver_propia']);
});

it('el Docente no recibe ningún permiso de boleta (mínimo privilegio)', function () {
    $this->seed(RolesYPermisosSeeder::class);

    expect(permisosEvaluacionDe('Docente'))->not->toContain('boletas.ver_todas')->not->toContain('boletas.ver_propia');
});

it('agrega siete permisos y deja treinta en total', function () {
    $this->seed(RolesYPermisosSeeder::class);

    expect(Permiso::where('identificador', 'like', 'notas.%')->count())->toBe(5)
        ->and(Permiso::where('identificador', 'like', 'boletas.%')->count())->toBe(2)
        ->and(Permiso::count())->toBe(30);
});

it('es idempotente y no pisa los cambios del Administrador', function () {
    $this->seed(RolesYPermisosSeeder::class);
    Rol::where('nombre', 'Director')->first()->permisos()->detach(Permiso::where('identificador', 'notas.ver_todas')->value('id'));

    $this->seed(RolesYPermisosSeeder::class);

    expect(Permiso::count())->toBe(30)
        ->and(permisosEvaluacionDe('Director'))->toBe(['boletas.ver_todas']);
});
