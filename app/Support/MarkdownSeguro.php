<?php

namespace App\Support;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/** Convierte el Markdown de las respuestas del asistente en HTML sin permitir HTML propio ni enlaces peligrosos (R-01). */
class MarkdownSeguro
{
    public static function html(string $texto): HtmlString
    {
        return new HtmlString(Str::markdown($texto, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 10,
            'renderer' => ['soft_break' => "<br>\n"],
        ]));
    }
}
