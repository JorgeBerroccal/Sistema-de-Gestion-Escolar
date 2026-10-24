<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PeriodoEvaluacion extends Model
{
    use HasFactory;

    protected $table = 'periodos_evaluacion';

    protected $fillable = ['anio_escolar_id', 'orden', 'nombre', 'fecha_inicio', 'fecha_fin'];

    protected function casts(): array
    {
        return ['orden' => 'integer', 'fecha_inicio' => 'date', 'fecha_fin' => 'date'];
    }

    public function anio(): BelongsTo
    {
        return $this->belongsTo(AnioEscolar::class, 'anio_escolar_id');
    }
}
