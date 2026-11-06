<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Catálogo de estados de asistencia (datos base, research R-01). */
class EstadoAsistencia extends Model
{
    use HasFactory;

    protected $table = 'estados_asistencia';

    protected $fillable = ['codigo', 'nombre', 'cuenta_asistencia', 'cuenta_falta', 'justificado_id', 'orden', 'activo'];

    protected function casts(): array
    {
        return ['cuenta_asistencia' => 'boolean', 'cuenta_falta' => 'boolean', 'activo' => 'boolean', 'orden' => 'integer'];
    }

    /** Estado al que pasa este al justificarse. */
    public function justificado(): BelongsTo
    {
        return $this->belongsTo(self::class, 'justificado_id');
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true)->orderBy('orden');
    }

    /** Estado origen de una justificación: el que apunta a este con `justificado_id`. */
    public function origenDeJustificacion(): ?self
    {
        return self::query()->where('justificado_id', $this->id)->first();
    }
}
