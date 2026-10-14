<?php

use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function consultasDelListado(int $usuarios): int
{
    Usuario::factory()->count($usuarios)->create();
    $admin = usuarioConPermisos(['usuarios.ver']);

    DB::flushQueryLog();
    DB::enableQueryLog();
    test()->actingAs($admin)->get('/admin/usuarios')->assertOk();
    $total = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $total;
}

it('ejecuta el mismo número de consultas con 5 y con 40 usuarios (sin N+1)', function () {
    $con5 = consultasDelListado(5);
    $con40 = consultasDelListado(35);

    expect($con40)->toBe($con5);
});

it('muestra como máximo 15 filas', function () {
    Usuario::factory()->count(40)->create();
    $admin = usuarioConPermisos(['usuarios.ver']);

    $html = $this->actingAs($admin)->get('/admin/usuarios')->assertOk()->getContent();

    expect(substr_count($html, '<tr'))->toBeLessThanOrEqual(16); // 15 filas + encabezado
});

it('tiene los índices requeridos', function () {
    $columnasIndexadas = collect(Schema::getIndexes('usuarios'))->pluck('columns')->flatten()->unique();

    foreach (['rol_id', 'activo', 'nombres', 'apellidos', 'username', 'email'] as $columna) {
        expect($columnasIndexadas->contains($columna))->toBeTrue("falta índice en usuarios.{$columna}");
    }

    $rolPermiso = collect(Schema::getIndexes('rol_permiso'))->pluck('columns')->flatten();
    expect($rolPermiso->contains('permiso_id'))->toBeTrue();
});
