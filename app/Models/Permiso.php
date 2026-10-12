<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use LogicException;

class Permiso extends Model
{
    use HasFactory;

    public const REGEX_IDENTIFICADOR = '/^[a-z_]+(\.[a-z_]+)+$/';

    protected $table = 'permisos';

    protected $fillable = ['identificador', 'nombre', 'descripcion'];

    protected static function booted(): void
    {
        static::updating(function (Permiso $permiso) {
            if ($permiso->isDirty('identificador')) {
                throw new LogicException('El identificador de un permiso es inmutable.');
            }
        });
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Rol::class, 'rol_permiso', 'permiso_id', 'rol_id')->withTimestamps();
    }
}
