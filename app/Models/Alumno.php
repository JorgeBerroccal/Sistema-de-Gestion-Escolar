<?php

namespace App\Models;

use App\Autorizacion\Pertenencia\RequierePertenencia;
use App\Matricula\Sexo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Alumno extends Model
{
    use HasFactory;
    use RequierePertenencia;

    /** Permisos marcadores de elegibilidad (research R-05); no conceden acceso. */
    public const PERMISO_ALUMNO = 'matricula.ser_alumno';

    public const PERMISO_APODERADO = 'matricula.ser_apoderado';

    protected $table = 'alumnos';

    protected $fillable = ['nombres', 'apellidos', 'tipo_documento_id', 'numero_documento', 'fecha_nacimiento', 'sexo', 'usuario_id', 'activo'];

    protected function casts(): array
    {
        return ['fecha_nacimiento' => 'date', 'sexo' => Sexo::class, 'activo' => 'boolean'];
    }

    protected function numeroDocumento(): Attribute
    {
        return Attribute::set(fn (?string $valor) => $valor === null ? null : mb_strtoupper(trim($valor)));
    }

    protected function nombreCompleto(): Attribute
    {
        return Attribute::get(fn () => trim("{$this->apellidos}, {$this->nombres}"));
    }

    public function tipoDocumento(): BelongsTo
    {
        return $this->belongsTo(TipoDocumento::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    /** Apoderados (usuarios) con su parentesco en el pivote. */
    public function apoderados(): BelongsToMany
    {
        return $this->belongsToMany(Usuario::class, 'alumno_apoderado', 'alumno_id', 'usuario_id')
            ->withPivot(['id', 'parentesco'])->withTimestamps();
    }

    public function matriculas(): HasMany
    {
        return $this->hasMany(Matricula::class);
    }

    /**
     * Búsqueda por nombres, apellidos (contiene) o número de documento (prefijo), sin distinguir mayúsculas
     * ni tildes (colación `utf8mb4_unicode_ci`; research R-12).
     */
    public function scopeBuscar(Builder $query, ?string $texto): Builder
    {
        $texto = trim((string) $texto);

        if ($texto === '') {
            return $query;
        }

        $escapado = addcslashes($texto, '\\%_');

        return $query->where(fn (Builder $q) => $q
            ->where('alumnos.nombres', 'like', "%{$escapado}%")
            ->orWhere('alumnos.apellidos', 'like', "%{$escapado}%")
            ->orWhere('alumnos.numero_documento', 'like', "{$escapado}%"));
    }

    public function tieneMatriculas(): bool
    {
        return $this->matriculas_exists ?? $this->matriculas()->exists();
    }
}
