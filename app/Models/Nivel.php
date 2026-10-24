<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
}
