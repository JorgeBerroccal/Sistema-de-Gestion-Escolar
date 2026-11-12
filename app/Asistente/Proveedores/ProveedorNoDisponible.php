<?php

namespace App\Asistente\Proveedores;

use RuntimeException;

/** Único error que los adaptadores lanzan hacia la capa superior: caída, cuota agotada, configuración incompleta. */
class ProveedorNoDisponible extends RuntimeException {}
