<?php

namespace App\Matricula;

use App\Models\Alumno;
use App\Models\Usuario;

/**
 * Textos de las pantallas propias (Mi ficha, Mi asistencia, Mis notas y el asistente) según los permisos de vínculo,
 * nunca según el nombre del rol (SC-005): con `matricula.ser_alumno` se habla de la propia ficha; con
 * `matricula.ser_apoderado`, de los estudiantes a cargo.
 */
final class VistaPropia
{
    /** Alumno sin otro vínculo: se le habla en primera persona de su ficha. */
    public static function esAlumno(Usuario $usuario): bool
    {
        return $usuario->tienePermiso(Alumno::PERMISO_ALUMNO) && ! $usuario->tienePermiso(Alumno::PERMISO_APODERADO);
    }

    public static function esApoderado(Usuario $usuario): bool
    {
        return $usuario->tienePermiso(Alumno::PERMISO_APODERADO) && ! $usuario->tienePermiso(Alumno::PERMISO_ALUMNO);
    }

    /** Aviso cuando la cuenta no tiene ninguna ficha o estudiante vinculado. */
    public static function avisoSinVinculo(Usuario $usuario): string
    {
        $contacto = 'Comunícate con la administración del colegio.';

        return match (true) {
            self::esAlumno($usuario) => 'Tu cuenta todavía no está vinculada a tu ficha de alumno. '.$contacto,
            self::esApoderado($usuario) => 'Tu cuenta todavía no tiene estudiantes vinculados. '.$contacto,
            default => 'Tu cuenta todavía no tiene fichas ni estudiantes vinculados. '.$contacto,
        };
    }
}
