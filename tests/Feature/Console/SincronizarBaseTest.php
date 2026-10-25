<?php

use App\Console\Commands\SincronizarBase;
use App\Models\EscalaCalificacion;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\AdministradorInicialSeeder;
use Database\Seeders\LoadTestSeeder;
use Database\Seeders\UsuariosDemoSeeder;

it('crea los permisos, roles y escalas base', function () {
    $this->artisan('sge:sincronizar-base')->assertSuccessful();

    expect(Rol::count())->toBe(5)
        ->and(Permiso::count())->toBe(13)
        ->and(EscalaCalificacion::count())->toBe(2);
});

it('dos ejecuciones dan el mismo resultado y no revierten cambios del Administrador', function () {
    $this->artisan('sge:sincronizar-base')->assertSuccessful();
    Rol::where('nombre', 'Docente')->first()->permisos()->detach(Permiso::where('identificador', 'docencia.dictar')->value('id'));

    $this->artisan('sge:sincronizar-base')->assertSuccessful();

    expect(Permiso::count())->toBe(13)
        ->and(EscalaCalificacion::count())->toBe(2)
        ->and(Rol::where('nombre', 'Docente')->first()->permisos()->pluck('identificador')->all())->not->toContain('docencia.dictar');
});

it('no crea ningún usuario: ni el Administrador inicial ni los de carga ni los de ejemplo', function () {
    config(['sge.admin' => ['username' => 'admin', 'password' => 'password-largo', 'email' => null]]);

    $this->artisan('sge:sincronizar-base')->assertSuccessful();

    expect(Usuario::count())->toBe(0);
});

it('su lista de seeders solo contiene cargas base y excluye las de usuarios', function () {
    expect(SincronizarBase::SEEDERS)->not->toContain(AdministradorInicialSeeder::class)
        ->and(SincronizarBase::SEEDERS)->not->toContain(LoadTestSeeder::class)
        ->and(SincronizarBase::SEEDERS)->not->toContain(UsuariosDemoSeeder::class);
});

it('acepta --force en producción', function () {
    app()['env'] = 'production';

    $this->artisan('sge:sincronizar-base --force')->assertSuccessful();

    expect(Permiso::count())->toBe(13);
});

it('en producción sin --force pide confirmación y no hace nada si se rechaza', function () {
    app()['env'] = 'production';

    $this->artisan('sge:sincronizar-base')
        ->expectsConfirmation('Are you sure you want to run this command?', 'no')
        ->assertFailed();

    expect(Permiso::count())->toBe(0);
});
