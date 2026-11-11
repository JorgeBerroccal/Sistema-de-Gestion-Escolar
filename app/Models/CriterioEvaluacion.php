<?php

namespace App\Models;

use App\Autorizacion\Pertenencia\RequierePertenencia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CriterioEvaluacion extends Model
{
    use HasFactory;
    use RequierePertenencia;

    protected $table = 'criterios_evaluacion';

    protected $fillable = ['seccion_id', 'curso_id', 'periodo_id', 'nombre', 'peso', 'orden'];

    protected function casts(): array
    {
        return ['peso' => 'decimal:2', 'orden' => 'integer'];
    }

    public function seccion(): BelongsTo
    {
        return $this->belongsTo(Seccion::class);
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(PeriodoEvaluacion::class, 'periodo_id');
    }

    public function notas(): HasMany
    {
        return $this->hasMany(Nota::class, 'criterio_id');
    }
}
