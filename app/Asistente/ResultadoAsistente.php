<?php

namespace App\Asistente;

/** Resultado de `AsistenteService::preguntar()` (ver `contracts/chat.md`); los textos son siempre en español. */
final readonly class ResultadoAsistente
{
    public const RESPUESTA = 'respuesta';

    public const ACLARACION = 'aclaracion';

    public const SIN_DATOS = 'sin_datos';

    public const NO_DISPONIBLE = 'no_disponible';

    public const LIMITE = 'limite';

    public const ERROR = 'error';

    public const INVALIDO = 'invalido';

    public function __construct(
        public string $tipo,
        public string $texto,
        public ?int $segundos = null,
    ) {}

    public function llamoAlProveedor(): bool
    {
        return in_array($this->tipo, [self::RESPUESTA, self::ERROR], true);
    }
}
