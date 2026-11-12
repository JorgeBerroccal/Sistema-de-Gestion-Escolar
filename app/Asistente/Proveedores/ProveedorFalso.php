<?php

namespace App\Asistente\Proveedores;

/**
 * Adaptador determinista para pruebas y desarrollo: nunca sale a la red. Guarda lo recibido para que las pruebas
 * inspeccionen exactamente lo que llegaría al proveedor (SC-002 a SC-004).
 */
class ProveedorFalso implements ProveedorIA
{
    /** @var list<list<array{rol: string, texto: string}>> una entrada por llamada */
    public array $recibidos = [];

    private bool $fallar = false;

    private bool $datosReales = true;

    public function responder(array $mensajes): RespuestaIA
    {
        $this->recibidos[] = $mensajes;

        if ($this->fallar) {
            throw new ProveedorNoDisponible('Fallo simulado del proveedor falso.');
        }

        $contexto = collect($mensajes)->where('rol', 'system')->skip(1)->pluck('texto')->implode("\n");

        return new RespuestaIA(trim("Respuesta de prueba en español con la información disponible.\n".$contexto), 'falso');
    }

    /** Es un adaptador de pruebas: en producción nunca admite datos reales, haga lo que haga la configuración. */
    public function admiteDatosReales(): bool
    {
        return $this->datosReales && ! app()->environment('production');
    }

    public function nombre(): string
    {
        return 'falso';
    }

    /** Simula (o deja de simular) un proveedor caído. */
    public function fallar(bool $fallar = true): static
    {
        $this->fallar = $fallar;

        return $this;
    }

    /** Permite probar el adaptador que declara no admitir datos reales (como OpenRouter con modelos gratuitos). */
    public function declararDatosReales(bool $admite): static
    {
        $this->datosReales = $admite;

        return $this;
    }

    public function llamadas(): int
    {
        return count($this->recibidos);
    }

    /** Todo lo recibido en todas las llamadas, como un solo texto, para buscar datos que no deben aparecer. */
    public function textoRecibido(): string
    {
        return collect($this->recibidos)->flatten(1)->pluck('texto')->implode("\n");
    }

    /** Solo lo que construye el servidor (instrucciones y contexto), sin lo que escribió el usuario. */
    public function textoDelSistema(): string
    {
        return collect($this->recibidos)->flatten(1)->where('rol', 'system')->pluck('texto')->implode("\n");
    }
}
