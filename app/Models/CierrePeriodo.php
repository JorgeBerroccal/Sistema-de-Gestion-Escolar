<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Fila presente = periodo cerrado y publicado para una sección (research R-05). */
class CierrePeriodo extends Model
{
    use HasFactory;

    protected $table = 'cierres_periodo';

    protected $fillable = ['seccion_id', 'periodo_id', 'cerrado_por', 'cerrado_en'];

    protected function casts(): array
    {
        return ['cerrado_en' => 'datetime'];
    }

    public function seccion(): BelongsTo
    {
        return $this->belongsTo(Seccion::class);
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(PeriodoEvaluacion::class, 'periodo_id');
    }

    public function cerrador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'cerrado_por');
    }
}
