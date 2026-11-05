<?php

namespace App\Models;

use App\Autorizacion\Pertenencia\RequierePertenencia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asistencia extends Model
{
    use HasFactory;
    use RequierePertenencia;

    protected $table = 'asistencias';

    protected $fillable = ['seccion_id', 'alumno_id', 'fecha', 'estado_id', 'observacion', 'motivo_justificacion', 'registrado_por'];

    protected function casts(): array
    {
        return ['fecha' => 'date'];
    }

    public function seccion(): BelongsTo
    {
        return $this->belongsTo(Seccion::class);
    }

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class);
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(EstadoAsistencia::class, 'estado_id');
    }

    public function registrador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'registrado_por');
    }
}
