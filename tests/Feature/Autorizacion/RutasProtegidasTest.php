<?php

use App\Models\AnioEscolar;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Routing\Route as RutaLaravel;
use Illuminate\Support\Facades\Route;

/** @return array<string, array{0: string, 1: string, 2: array<string, mixed>}> */
function rutasConPermiso(): array
{
    $rutas = [];
    foreach (Route::getRoutes() as $ruta) {
        /** @var RutaLaravel $ruta */
        foreach ($ruta->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, 'can:')) {
                $rutas[] = [$ruta->uri(), substr($middleware, 4)];
            }
        }
    }

    return $rutas;
}

function urlConParametros(string $uri): string
{
    $rol = Rol::factory()->create();
    $usuario = Usuario::factory()->create();

    $parametros = ['{usuario}' => $usuario->id, '{rol}' => $rol->id];

    if (str_contains($uri, '{anio}')) {
        $parametros['{anio}'] = AnioEscolar::factory()->create()->id;
    }

    return '/'.ltrim(strtr($uri, $parametros), '/');
}

it('hay rutas protegidas con can:*', function () {
    expect(rutasConPermiso())->not->toBeEmpty();
});

it('cada ruta can:* da 403 sin el permiso y no da 403 con exactamente ese permiso', function () {
    foreach (rutasConPermiso() as [$uri, $permiso]) {
        $url = urlConParametros($uri);

        $this->actingAs(usuarioConPermisos([]))->get($url)->assertForbidden();

        $this->actingAs(usuarioConPermisos([$permiso]))->get($url)
            ->assertStatus(200); // con el permiso la ruta responde (no 403/404)
    }
});
