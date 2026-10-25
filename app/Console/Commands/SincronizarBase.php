<?php

namespace App\Console\Commands;

use Database\Seeders\EscalasCalificacionSeeder;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;

/**
 * Sincroniza los datos base que el sistema necesita en todo entorno (permisos, roles y escalas).
 * Lo ejecuta deploy.sh tras migrar. Solo crea lo que falta: los permisos eliminados o renombrados
 * se limpian a mano.
 */
class SincronizarBase extends Command
{
    use ConfirmableTrait;

    /**
     * Lista fija de seeders base idempotentes. Nunca el Administrador inicial, los usuarios de ejemplo
     * ni los de carga: no son datos base y no deben ejecutarse en cada despliegue.
     *
     * @var array<int, class-string>
     */
    public const SEEDERS = [
        RolesYPermisosSeeder::class,
        EscalasCalificacionSeeder::class,
    ];

    protected $signature = 'sge:sincronizar-base {--force : Ejecutar sin confirmar en producción}';

    protected $description = 'Crea los permisos, roles y escalas base que falten (idempotente)';

    public function handle(): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        foreach (self::SEEDERS as $seeder) {
            $this->call('db:seed', ['--class' => $seeder, '--force' => true]);
        }

        $this->components->info('Datos base sincronizados.');

        return self::SUCCESS;
    }
}
