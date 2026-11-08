<?php

namespace App\Asistencia;

use RuntimeException;

/**
 * Una regla de asistencia no se cumple. El mensaje se muestra tal cual y NUNCA lleva nombres ni documentos de
 * alumnos (Ley 29733): se identifica por posición en la lista.
 */
class AsistenciaIncumplida extends RuntimeException {}
