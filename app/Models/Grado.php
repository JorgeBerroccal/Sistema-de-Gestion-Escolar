<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Grado extends Model
{
    use HasFactory;

    protected $table = 'grados';

    protected $fillable = ['nivel_id', 'nombre', 'orden', 'activo'];

    protected function casts(): array
    {
        return ['orden' => 'integer', 'activo' => 'boolean'];
    }

    protected function nombre(): Attribute
    {
        return Attribute::set(fn (?string $valor) => $valor === null ? null : preg_replace('/\s+/u', ' ', trim($valor)));
    }

    public function nivel(): BelongsTo
    {
        return $this->belongsTo(Nivel::class);
    }

    public function secciones(): HasMany
    {
        return $this->hasMany(Seccion::class);
    }

    public function cursos(): BelongsToMany
    {
        return $this->belongsToMany(Curso::class, 'curso_grado');
    }

    public function tieneDependientes(): bool
    {
        return ($this->secciones_exists ?? $this->secciones()->exists()) || ($this->cursos_exists ?? $this->cursos()->exists());
    }
}
