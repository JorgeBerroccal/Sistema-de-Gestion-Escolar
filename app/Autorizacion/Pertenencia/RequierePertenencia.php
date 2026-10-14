<?php

namespace App\Autorizacion\Pertenencia;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** Declara que un modelo requiere filtro de pertenencia; sin regla registrada todo se deniega. */
trait RequierePertenencia
{
    public function scopeVisiblePara(Builder $query, Usuario $usuario): Builder
    {
        return $this->reglaDePertenencia()->aplicar($query, $usuario);
    }

    public function autorizarPertenencia(Usuario $usuario): void
    {
        if (! $this->reglaDePertenencia()->permite($usuario, $this)) {
            throw new AccessDeniedHttpException('No tienes acceso a este registro.');
        }
    }

    private function reglaDePertenencia(): ReglaPertenencia
    {
        return app(RegistroReglasPertenencia::class)->reglaPara(static::class)
            ?? throw new ReglaPertenenciaFaltante(static::class);
    }
}
