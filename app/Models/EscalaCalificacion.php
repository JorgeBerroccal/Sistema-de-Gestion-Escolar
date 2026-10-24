<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EscalaCalificacion extends Model
{
    protected $table = 'escalas_calificacion';

    protected $fillable = ['codigo', 'nombre', 'valores'];

    protected function casts(): array
    {
        return ['valores' => 'array'];
    }
}
