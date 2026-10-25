<?php

namespace App\Models;

use App\Autorizacion\Pertenencia\RequierePertenencia;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AsignacionDocente extends Model
{
    use HasFactory;
    use RequierePertenencia;

    /** Permiso marcador de elegibilidad como docente (research R-05); no concede acceso. */
    public const PERMISO_DOCENTE = 'docencia.dictar';

    protected $table = 'asignaciones_docentes';

    protected $fillable = ['seccion_id', 'curso_id', 'usuario_id'];

    public function seccion(): BelongsTo
    {
        return $this->belongsTo(Seccion::class);
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    /** Estado derivado (R-06): el docente está inactivo o su rol ya no tiene el permiso marcador. */
    protected function pendienteReasignar(): Attribute
    {
        return Attribute::get(fn () => ! $this->usuario->tienePermiso(self::PERMISO_DOCENTE));
    }
}
