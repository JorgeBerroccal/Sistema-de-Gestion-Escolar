<?php

use App\Support\MarkdownSeguro;

it('interpreta negrita, listas y saltos de línea', function () {
    $html = (string) MarkdownSeguro::html("**Tecnología**\n\n- uno\n- dos");

    expect($html)->toContain('<strong>Tecnología</strong>')->toContain('<li>uno</li>')->not->toContain('**');
});

it('escapa el HTML propio, incluido <script>', function () {
    $html = (string) MarkdownSeguro::html('hola <script>alert(1)</script> <img src=x onerror=alert(1)>');

    expect($html)->not->toContain('<script')->not->toContain('<img')->toContain('&lt;script&gt;');
});

it('no genera enlaces con javascript:, data: ni vbscript:', function () {
    foreach (['javascript:alert(1)', 'JaVaScRiPt:alert(1)', 'data:text/html;base64,PHNjcmlwdD4=', 'vbscript:msgbox(1)'] as $url) {
        expect((string) MarkdownSeguro::html("[clic]({$url})"))->not->toMatch('/href="(javascript|data|vbscript):/i');
    }
});

it('conserva los enlaces https', function () {
    expect((string) MarkdownSeguro::html('[sitio](https://ejemplo.pe)'))->toContain('href="https://ejemplo.pe"');
});
