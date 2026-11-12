<?php

namespace App\Asistente;

use App\Models\Usuario;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Límite de solicitudes (research R-05): por usuario y global, por minuto y por día, sobre el `RateLimiter` de Laravel
 * (el mismo que usa el login). El tope global protege la cuota conjunta del plan gratuito del proveedor, que el
 * límite por usuario por sí solo no garantiza.
 */
class LimiteSolicitudes
{
    private const MINUTO = 60;

    private const DIA = 86400;

    /**
     * Reserva un cupo de solicitud. Devuelve `null` si hay cupo (y lo consume), o los segundos que faltan para poder
     * volver a preguntar si algún límite está superado (sin consumir nada).
     */
    public function intentar(Usuario $usuario): ?int
    {
        $limites = $this->limites($usuario);

        $espera = collect($limites)
            ->filter(fn (array $l) => RateLimiter::tooManyAttempts($l['clave'], $l['maximo']))
            ->map(fn (array $l) => RateLimiter::availableIn($l['clave']))
            ->max();

        if ($espera !== null) {
            return max(1, (int) $espera);
        }

        foreach ($limites as $l) {
            RateLimiter::hit($l['clave'], $l['ventana']);
        }

        return null;
    }

    /** @return list<array{clave: string, maximo: int, ventana: int}> */
    private function limites(Usuario $usuario): array
    {
        $c = config('asistente.limites');

        return [
            ['clave' => "asistente:usuario:{$usuario->id}:minuto", 'maximo' => (int) $c['usuario_minuto'], 'ventana' => self::MINUTO],
            ['clave' => "asistente:usuario:{$usuario->id}:dia", 'maximo' => (int) $c['usuario_dia'], 'ventana' => self::DIA],
            ['clave' => 'asistente:global:minuto', 'maximo' => (int) $c['global_minuto'], 'ventana' => self::MINUTO],
            ['clave' => 'asistente:global:dia', 'maximo' => (int) $c['global_dia'], 'ventana' => self::DIA],
        ];
    }

    /** Texto de espera en español: segundos, minutos u horas. */
    public static function textoEspera(int $segundos): string
    {
        return match (true) {
            $segundos < 60 => $segundos.' '.($segundos === 1 ? 'segundo' : 'segundos'),
            $segundos < 3600 => ($m = (int) ceil($segundos / 60)).' '.($m === 1 ? 'minuto' : 'minutos'),
            default => ($h = (int) ceil($segundos / 3600)).' '.($h === 1 ? 'hora' : 'horas'),
        };
    }
}
