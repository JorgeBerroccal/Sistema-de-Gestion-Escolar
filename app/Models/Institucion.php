<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** La única ficha de la instalación (id fijo en 1; la base lo exige con un CHECK). */
class Institucion extends Model
{
    use HasFactory;

    public const ID = 1;

    protected $table = 'instituciones';

    public $incrementing = false;

    protected $fillable = ['nombre', 'codigo_modular', 'ugel_dre', 'escala_calificacion_id'];

    /** La ficha guardada o una nueva (sin guardar) con el id fijo. */
    public static function actual(): self
    {
        return static::find(self::ID) ?? tap(new static, fn (self $ficha) => $ficha->id = self::ID);
    }

    public function niveles(): BelongsToMany
    {
        return $this->belongsToMany(Nivel::class, 'institucion_nivel', 'institucion_id', 'nivel_id');
    }

    public function escala(): BelongsTo
    {
        return $this->belongsTo(EscalaCalificacion::class, 'escala_calificacion_id');
    }
}
