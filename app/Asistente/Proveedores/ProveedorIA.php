<?php

namespace App\Asistente\Proveedores;

/**
 * Puerto del proveedor de IA (Constitución 2.3.0, Principio VI). El asistente nunca conoce al proveedor concreto:
 * los adaptadores se eligen por configuración (`config('asistente.proveedor')`).
 */
interface ProveedorIA
{
    /**
     * @param  list<array{rol: string, texto: string}>  $mensajes  `rol` es `system`, `user` o `assistant`
     *
     * @throws ProveedorNoDisponible cualquier fallo del proveedor, sin detalles técnicos para el usuario
     */
    public function responder(array $mensajes): RespuestaIA;

    /** Si el adaptador (con su configuración actual) puede recibir datos reales de alumnos. */
    public function admiteDatosReales(): bool;

    /** Nombre corto para metadatos de registro (nunca el contenido de la conversación). */
    public function nombre(): string;
}
