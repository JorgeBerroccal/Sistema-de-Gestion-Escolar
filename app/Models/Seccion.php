<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Seccion extends Model
{
    use HasFactory;

    protected $table = 'secciones';

    protected $fillable = ['grado_id', 'anio_escolar_id', 'identificador', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    protected function identificador(): Attribute
    {
        return Attribute::set(fn (?string $valor) => $valor === null ? null : mb_strtoupper(preg_replace('/\s+/u', ' ', trim($valor))));
    }

    public function grado(): BelongsTo
    {
        return $this->belongsTo(Grado::class);
    }

    public function anio(): BelongsTo
    {
        return $this->belongsTo(AnioEscolar::class, 'anio_escolar_id');
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(AsignacionDocente::class, 'seccion_id');
    }

    public function tieneDependientes(): bool
    {
        return $this->asignaciones_exists ?? $this->asignaciones()->exists();
    }
}
