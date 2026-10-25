<?php

namespace App\Models;

use App\Academico\TipoPeriodo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnioEscolar extends Model
{
    use HasFactory;

    protected $table = 'anios_escolares';

    /** `vigente` no es asignable en masa: solo AnioEscolarService::marcarVigente() lo cambia. */
    protected $fillable = ['nombre', 'fecha_inicio', 'fecha_fin', 'tipo_periodo', 'activo'];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'tipo_periodo' => TipoPeriodo::class,
            'activo' => 'boolean',
        ];
    }

    protected function nombre(): Attribute
    {
        return Attribute::set(fn (?string $valor) => $valor === null ? null : preg_replace('/\s+/u', ' ', trim($valor)));
    }

    public function esVigente(): bool
    {
        return $this->vigente !== null;
    }

    public function periodos(): HasMany
    {
        return $this->hasMany(PeriodoEvaluacion::class, 'anio_escolar_id')->orderBy('orden');
    }

    public function secciones(): HasMany
    {
        return $this->hasMany(Seccion::class, 'anio_escolar_id');
    }

    /** Hoy solo las secciones; los Módulos 4 y 5 sumarán asistencia y notas de sus periodos. */
    public function tieneDependientes(): bool
    {
        return $this->secciones_exists ?? $this->secciones()->exists();
    }
}
