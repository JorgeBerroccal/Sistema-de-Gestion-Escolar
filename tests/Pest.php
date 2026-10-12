<?php

use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Las pruebas Unit pueden usar el contenedor y config(), pero nunca la base de datos.
pest()->extend(TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Crea un usuario activo cuyo rol (de fábrica) tiene exactamente los permisos indicados
 * por identificador; los permisos que no existan se crean.
 *
 * @param  array<int, string>  $identificadores
 * @param  array<string, mixed>  $atributos
 */
function usuarioConPermisos(array $identificadores = [], array $atributos = []): Usuario
{
    $rol = Rol::factory()->create();

    $ids = collect($identificadores)->map(fn (string $identificador) => Permiso::firstOrCreate(
        ['identificador' => $identificador],
        ['nombre' => $identificador],
    )->id);

    $rol->permisos()->sync($ids->all());

    return Usuario::factory()->create(array_merge(['rol_id' => $rol->id, 'activo' => true], $atributos));
}
