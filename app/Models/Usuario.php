<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Usuario extends Authenticatable
{
    use HasFactory;

    public const REGEX_USERNAME = '/^[a-z0-9._-]{3,50}$/';

    protected $table = 'usuarios';

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

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'rol_id');
    }
}
