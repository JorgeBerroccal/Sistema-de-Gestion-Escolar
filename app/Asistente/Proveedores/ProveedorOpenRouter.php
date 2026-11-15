<?php

namespace App\Asistente\Proveedores;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Adaptador de OpenRouter (API compatible con OpenAI), research R-01 a R-03. El modelo y la lista de respaldo vienen de
 * la configuración: OpenRouter prueba los modelos de `models` en orden dentro de UNA solicitud, de modo que el
 * respaldo no gasta cupo extra del plan gratuito. Con modelos gratuitos declara que NO admite datos reales.
 */
class ProveedorOpenRouter implements ProveedorIA
{
    /** @param  list<string>  $respaldo */
    public function __construct(
        private readonly ?string $clave,
        private readonly string $url,
        private readonly ?string $modelo,
        private readonly array $respaldo,
        private readonly bool $datosReales,
        private readonly int $timeout,
    ) {}

    public static function desdeConfiguracion(): self
    {
        $c = config('asistente.openrouter');

        return new self($c['clave'], $c['url'], $c['modelo'], $c['respaldo'], (bool) $c['datos_reales'], (int) $c['timeout']);
    }

    public function responder(array $mensajes): RespuestaIA
    {
        if (blank($this->clave) || blank($this->modelo)) {
            throw new ProveedorNoDisponible('Configuración incompleta del proveedor.');
        }

        $cuerpo = [
            'model' => $this->modelo,
            'messages' => array_map(fn (array $m) => ['role' => $m['rol'], 'content' => $m['texto']], $mensajes),
            'temperature' => 0.2,
            'max_tokens' => 500,
        ];

        if ($this->respaldo !== []) {
            $cuerpo['models'] = $this->modelos();
        }

        try {
            $respuesta = Http::withToken($this->clave)->acceptJson()->asJson()->timeout($this->timeout)
                ->post(rtrim($this->url, '/').'/chat/completions', $cuerpo);
        } catch (Throwable) {
            throw new ProveedorNoDisponible('No se pudo contactar al proveedor.');
        }

        $json = $respuesta->json();
        $texto = data_get($json, 'choices.0.message.content');

        if (! $respuesta->successful() || data_get($json, 'error') !== null || ! is_string($texto) || trim($texto) === '') {
            throw new ProveedorNoDisponible('El proveedor no devolvió una respuesta válida.');
        }

        $modelo = data_get($json, 'model');

        return new RespuestaIA(trim($texto), is_string($modelo) ? $modelo : $this->modelo);
    }

    public function admiteDatosReales(): bool
    {
        return $this->datosReales && ! collect($this->modelos())->contains(fn (string $m) => str_ends_with($m, ':free'));
    }

    public function nombre(): string
    {
        return 'openrouter';
    }

    /** @return list<string> el modelo principal seguido de los de respaldo */
    private function modelos(): array
    {
        return array_values(array_filter([$this->modelo, ...$this->respaldo], fn ($m) => filled($m)));
    }
}
