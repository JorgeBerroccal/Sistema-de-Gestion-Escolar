<?php

namespace Tests\Support\Pertenencia;

use App\Autorizacion\Pertenencia\ReglaPertenencia;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ReglaDePrueba implements ReglaPertenencia
{
    public function aplicar(Builder $query, Usuario $usuario): Builder
    {
        return $query->where('usuario_id', $usuario->id);
    }

    public function permite(Usuario $usuario, Model $registro): bool
    {
        return $registro->usuario_id === $usuario->id;
    }
}
