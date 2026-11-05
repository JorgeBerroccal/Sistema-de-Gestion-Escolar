<?php

namespace App\Models;

use App\Autorizacion\Pertenencia\RequierePertenencia;
use App\Matricula\EstadoMatricula;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Matricula extends Model
{
    use HasFactory;
    use RequierePertenencia;

    protected $table = 'matriculas';

    protected $fillable = ['alumno_id', 'seccion_id', 'anio_id', 'fecha_matricula', 'estado', 'fecha_estado', 'motivo'];

    protected function casts(): array
    {
        return ['fecha_matricula' => 'date', 'fecha_estado' => 'date', 'estado' => EstadoMatricula::class];
    }

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class);
    }

    public function seccion(): BelongsTo
    {
        return $this->belongsTo(Seccion::class);
    }

    public function anio(): BelongsTo
    {
        return $this->belongsTo(AnioEscolar::class, 'anio_id');
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where($query->getModel()->qualifyColumn('estado'), EstadoMatricula::Matriculado->value);
    }
}
