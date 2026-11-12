<?php

namespace App\Support;

/** Números tal como los ve el usuario: coma decimal y sin separador de miles («87,5 %», «13,00»). */
final class Formato
{
    /** Número con exactamente `$decimales` decimales. */
    public static function decimal(float|int|string $numero, int $decimales = 2): string
    {
        return number_format((float) $numero, $decimales, ',', '');
    }

    /** Número sin ceros de más: `60.00` → `60`, `15.50` → `15,5`, `87.5` → `87,5`. */
    public static function recortado(float|int|string|null $numero): string
    {
        $texto = (string) ($numero ?? '');

        if (str_contains($texto, '.')) {
            $texto = rtrim(rtrim($texto, '0'), '.');
        }

        return str_replace('.', ',', $texto === '' ? '0' : $texto);
    }
}
