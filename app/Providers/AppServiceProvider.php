<?php

namespace App\Providers;

use App\Asistente\Proveedores\ProveedorFalso;
use App\Asistente\Proveedores\ProveedorIA;
use App\Asistente\Proveedores\ProveedorOpenRouter;
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

        // El adaptador de IA se elige por configuración; un valor desconocido es un error claro, no un silencio.
        $this->app->singleton(ProveedorIA::class, fn () => match ($proveedor = config('asistente.proveedor')) {
            'falso' => new ProveedorFalso,
            'openrouter' => ProveedorOpenRouter::desdeConfiguracion(),
            default => throw new \InvalidArgumentException("Proveedor de IA desconocido: «{$proveedor}». Usa «falso» u «openrouter» en ASISTENTE_PROVEEDOR."),
        });
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
