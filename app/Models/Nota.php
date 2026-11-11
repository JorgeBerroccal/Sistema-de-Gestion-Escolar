<?php

namespace App\Models;

use App\Autorizacion\Pertenencia\RequierePertenencia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Nota extends Model
{
    use HasFactory;
    use RequierePertenencia;

    protected $table = 'notas';

    protected $fillable = ['criterio_id', 'alumno_id', 'valor', 'valor_numerico', 'registrado_por'];

    protected function casts(): array
    {
        return ['valor_numerico' => 'decimal:2'];
    }

    public function criterio(): BelongsTo
    {
        return $this->belongsTo(CriterioEvaluacion::class, 'criterio_id');
    }

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class);
    }

    public function registrador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'registrado_por');
    }
}
