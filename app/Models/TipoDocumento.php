<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Tipo de documento de identidad y su regla de formato (datos base, FR-002). */
class TipoDocumento extends Model
{
    use HasFactory;

    protected $table = 'tipos_documento';

    protected $fillable = ['codigo', 'nombre', 'longitud_min', 'longitud_max', 'solo_digitos', 'activo'];

    protected function casts(): array
    {
        return ['solo_digitos' => 'boolean', 'activo' => 'boolean'];
    }

    public function alumnos(): HasMany
    {
        return $this->hasMany(Alumno::class);
    }

    /** Mensaje de error del número según la regla del tipo, o null si es válido. */
    public function errorDeFormato(string $numero): ?string
    {
        $largo = mb_strlen($numero);
        $exacto = $this->longitud_min === $this->longitud_max;

        if ($this->solo_digitos && ! ctype_digit($numero)) {
            return "El número de {$this->codigo} debe tener solo dígitos.";
        }

        if (! $this->solo_digitos && preg_match('/^[A-Z0-9]+$/', $numero) !== 1) {
            return "El número de {$this->codigo} solo admite letras y dígitos.";
        }

        if ($largo < $this->longitud_min || $largo > $this->longitud_max) {
            return $exacto
                ? "El número de {$this->codigo} debe tener exactamente {$this->longitud_min} ".($this->solo_digitos ? 'dígitos' : 'caracteres').'.'
                : "El número de {$this->codigo} debe tener entre {$this->longitud_min} y {$this->longitud_max} caracteres.";
        }

        return null;
    }
}
