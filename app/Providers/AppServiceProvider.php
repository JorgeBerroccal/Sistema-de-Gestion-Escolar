<?php

namespace App\Providers;

use App\Autorizacion\Pertenencia\RegistroReglasPertenencia;
use App\Http\Middleware\EnsureUserIsActive;
use App\Models\Usuario;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(RegistroReglasPertenencia::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Todo se resuelve contra rol_permiso. Devolver null (no false) deja que Laravel siga su
        // curso: una habilidad sin definir se deniega (research R-05).
        Gate::before(fn (Usuario $usuario, string $habilidad) => $usuario->tienePermiso($habilidad) ? true : null);

        // Las acciones Livewire usan su propio endpoint: también deben desconectar cuentas inactivas (R-03).
        Livewire::addPersistentMiddleware([EnsureUserIsActive::class]);
    }
}
