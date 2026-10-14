<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;

class Usuario extends Authenticatable
{
    use HasFactory;

    public const REGEX_USERNAME = '/^[a-z0-9._-]{3,50}$/';

    protected $table = 'usuarios';

    /**
     * Sugerencia de username: primer nombre + "." + primer apellido, en minúsculas, sin tildes
     * (la ñ pasa a n) y solo con caracteres de REGEX_USERNAME. Vacío si no hay una sugerencia válida.
     */
    public static function sugerirUsername(string $nombres, string $apellidos): string
    {
        $primera = fn (string $texto) => preg_replace(
            '/[^a-z0-9._-]/',
            '',
            mb_strtolower(Str::ascii(preg_split('/\s+/', trim($texto))[0] ?? '')),
        );

        $sugerido = mb_substr(implode('.', array_filter([$primera($nombres), $primera($apellidos)])), 0, 50);

        return preg_match(self::REGEX_USERNAME, $sugerido) === 1 ? $sugerido : '';
    }

    protected $fillable = ['nombres', 'apellidos', 'username', 'email', 'password', 'rol_id', 'activo'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'activo' => 'boolean',
        ];
    }

    protected function username(): Attribute
    {
        return Attribute::set(fn (?string $valor) => $valor === null ? null : mb_strtolower(trim($valor)));
    }

    protected function email(): Attribute
    {
        return Attribute::set(function (?string $valor) {
            $valor = $valor === null ? '' : mb_strtolower(trim($valor));

            return $valor === '' ? null : $valor;
        });
    }

    protected function nombreCompleto(): Attribute
    {
        return Attribute::get(fn () => trim("{$this->nombres} {$this->apellidos}"));
    }

    /** @var array<int, string>|null identificadores del rol, memoizados por instancia (nunca entre solicitudes) */
    protected ?array $identificadoresPermiso = null;

    /** Una cuenta inactiva no tiene ningún permiso (segunda defensa; research R-03/R-05). */
    public function tienePermiso(string $identificador): bool
    {
        if (! $this->activo) {
            return false;
        }

        $this->identificadoresPermiso ??= $this->rol->permisos->pluck('identificador')->all();

        return in_array($identificador, $this->identificadoresPermiso, true);
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'rol_id');
    }
}
