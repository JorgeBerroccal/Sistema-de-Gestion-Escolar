<?php

namespace App\Asistente;

use App\Asistente\Proveedores\ProveedorIA;

/**
 * Falla cerrado (research R-04): en producción, un adaptador que no declara admitir datos reales (p. ej. OpenRouter con
 * modelos gratuitos) nunca recibe datos de alumnos; el asistente se niega a funcionar con un mensaje claro.
 */
class Disponibilidad
{
    public function __construct(private readonly ProveedorIA $proveedor) {}

    /** Mensaje de negativa en español, o `null` si el asistente puede funcionar. */
    public function verificar(): ?string
    {
        if (app()->environment('production') && ! $this->proveedor->admiteDatosReales()) {
            return 'El asistente virtual está deshabilitado en este entorno porque el proveedor de IA configurado no está autorizado para usar datos reales de estudiantes. Consulta al administrador del sistema.';
        }

        return null;
    }
}
