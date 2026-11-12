<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Hora y día «de pared» de la institución. `app.timezone` sigue en UTC (lo guardado y calculado); todo lo que ve el
 * usuario —fechas y horas impresas, y qué día es «hoy»— se expresa en la zona configurada (`ASISTENCIA_ZONA_HORARIA`,
 * por defecto America/Lima), la misma que decide el «hoy» de la asistencia.
 */
final class ZonaHoraria
{
    public static function nombre(): string
    {
        return config('asistencia.zona_horaria');
    }

    /** Instante actual con la zona de la institución. */
    public static function ahora(): CarbonImmutable
    {
        return CarbonImmutable::now(self::nombre());
    }

    /** `Y-m-d` de hoy en la institución (no el de UTC). */
    public static function hoy(): string
    {
        return self::ahora()->toDateString();
    }

    /** Lleva un instante guardado en UTC a la zona de la institución para mostrarlo. */
    public static function local(\DateTimeInterface $instante): CarbonImmutable
    {
        return CarbonImmutable::instance($instante)->setTimezone(self::nombre());
    }
}
