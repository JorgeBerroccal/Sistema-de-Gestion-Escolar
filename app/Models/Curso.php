<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Curso extends Model
{
    use HasFactory;

    protected $table = 'cursos';

    protected $fillable = ['nivel_id', 'nombre', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    protected function nombre(): Attribute
    {
        return Attribute::set(fn (?string $valor) => $valor === null ? null : preg_replace('/\s+/u', ' ', trim($valor)));
    }

    public function nivel(): BelongsTo
    {
        return $this->belongsTo(Nivel::class);
    }

    public function grados(): BelongsToMany
    {
        return $this->belongsToMany(Grado::class, 'curso_grado');
    }
}
