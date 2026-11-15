# Contrato: puerto `ProveedorIA`

Interfaz interna (no HTTP público) que separa el asistente del proveedor concreto (Constitución 2.3.0, VI).

```php
interface ProveedorIA
{
    /** @param array<int, array{rol: 'system'|'user'|'assistant', texto: string}> $mensajes */
    public function responder(array $mensajes): RespuestaIA;   // lanza ProveedorNoDisponible

    public function admiteDatosReales(): bool;

    public function nombre(): string;   // para metadatos de log (nunca el contenido)
}
```

| Regla | Detalle |
|---|---|
| Resolución | `config('asistente.proveedor')` → `falso` \| `openrouter`; valor desconocido = fallo de configuración claro, no silencioso. |
| Errores | Cualquier fallo de red, HTTP ≠ 2xx, cuota agotada, respuesta sin texto o configuración incompleta se convierte en `ProveedorNoDisponible`; el mensaje al usuario no revela detalles técnicos ni la clave. |
| Datos reales | Falso: `true`. OpenRouter: `false` si algún modelo termina en `:free` o `datos_reales` no es `true`. |

## `ProveedorFalso`

Determinista: responde en español, de forma estable, con un texto derivado del contexto recibido (p. ej.
repitiendo cifras del contexto), e incluye un interruptor para simular fallo (`ProveedorFalso::fallar()`).
Guarda los `mensajes` recibidos para que las pruebas inspeccionen el payload (SC-002 a SC-004).

## `ProveedorOpenRouter`

`POST {url}/chat/completions`, JSON: `model`, `models` (respaldo, si hay), `messages` (`role`/`content`),
`temperature` baja, `max_tokens` acotado. Respuesta: `choices[0].message.content` y `model` usado.
`Authorization: Bearer <clave>`; la clave nunca se registra. Las pruebas usan `Http::fake()`.
