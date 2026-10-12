<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public const MAX_INTENTOS = 5;

    public const VENTANA_SEGUNDOS = 60;

    public const MENSAJE_INACTIVA = 'Tu cuenta está inactiva. Contacta al administrador.';

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string'],
        ];
    }

    /** `email` si el login contiene `@`; si no, `username` (research R-02). */
    public static function columnaPara(string $login): string
    {
        return str_contains($login, '@') ? 'email' : 'username';
    }

    public static function claveLimite(string $login, string $ip): string
    {
        return 'login|'.mb_strtolower(trim($login)).'|'.$ip;
    }

    /**
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $login = mb_strtolower(trim($this->string('login')->toString()));
        $clave = self::claveLimite($login, (string) $this->ip());

        if (RateLimiter::tooManyAttempts($clave, self::MAX_INTENTOS)) {
            throw ValidationException::withMessages([
                'login' => 'Demasiados intentos. Intenta nuevamente en '.RateLimiter::availableIn($clave).' segundos.',
            ]);
        }

        $credenciales = [self::columnaPara($login) => $login, 'password' => $this->input('password')];

        if (! Auth::attempt($credenciales)) {
            RateLimiter::hit($clave, self::VENTANA_SEGUNDOS);

            throw ValidationException::withMessages(['login' => 'Las credenciales no son válidas.']);
        }

        if (! Auth::user()->activo) {
            Auth::logout();

            throw ValidationException::withMessages(['login' => self::MENSAJE_INACTIVA]);
        }

        RateLimiter::clear($clave);
    }
}
