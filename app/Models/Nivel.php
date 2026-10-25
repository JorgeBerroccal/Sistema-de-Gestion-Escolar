<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Nivel extends Model
{
    use HasFactory;

    protected $table = 'niveles';

    protected $fillable = ['nombre', 'orden', 'activo'];

    protected function casts(): array
    {
        return ['orden' => 'integer', 'activo' => 'boolean'];
    }

    protected function nombre(): Attribute
    {
        return Attribute::set(fn (?string $valor) => $valor === null ? null : preg_replace('/\s+/u', ' ', trim($valor)));
    }

    public function grados(): HasMany
    {
        return $this->hasMany(Grado::class);
    }

    public function tieneGradosActivos(): bool
    {
        return $this->grados()->where('activo', true)->exists();
    }

    public function cursos(): HasMany
    {
        return $this->hasMany(Curso::class);
    }

    public function instituciones(): BelongsToMany
    {
        return $this->belongsToMany(Institucion::class, 'institucion_nivel', 'nivel_id', 'institucion_id');
    }

    /**
     * Grados, cursos o una institución que lo atiende impiden eliminarlo (solo desactivarlo).
     * Los listados precargan `grados_exists`, `cursos_exists` e `instituciones_exists` para no consultar por fila.
     */
    public function tieneDependientes(): bool
    {
        return ($this->grados_exists ?? $this->grados()->exists())
            || ($this->cursos_exists ?? $this->cursos()->exists())
            || ($this->instituciones_exists ?? $this->instituciones()->exists());
    }
}
