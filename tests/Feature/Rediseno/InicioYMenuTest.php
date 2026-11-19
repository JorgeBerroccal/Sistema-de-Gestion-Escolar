<?php

use App\Models\AnioEscolar;
use App\Models\Institucion;
use App\Support\Menu;

it('las tarjetas del Inicio coinciden con los ítems agrupados del menú para cada rol', function (string $rol) {
    $usuario = usuarioConRolDeSeeder($rol);
    $esperadas = collect(Menu::gruposPara($usuario))->flatMap(fn ($g) => $g['items'])->pluck('etiqueta')->all();

    $html = $this->actingAs($usuario)->get('/inicio')->assertOk()->getContent();
    preg_match_all('/<a [^>]*data-tarjeta[^>]*>.*?<span class="font-semibold text-slate-900">(.*?)<\/span>/s', $html, $m);

    expect(array_map('html_entity_decode', $m[1]))->toBe($esperadas)->and($esperadas)->not->toBeEmpty();
})->with(['Administrador', 'Director', 'Docente', 'Padre de Familia', 'Alumno']);

it('el menú lateral lista los mismos ítems que el Inicio, agrupados, más Inicio suelto', function () {
    $usuario = usuarioConRolDeSeeder('Administrador');
    $html = $this->actingAs($usuario)->get('/inicio')->assertOk()->getContent();

    foreach (Menu::gruposPara($usuario) as $grupo) {
        expect($html)->toContain($grupo['nombre']);
        foreach ($grupo['items'] as $item) {
            expect($html)->toContain('href="'.route($item['ruta']).'"');
        }
    }
    expect($html)->toContain('aria-label="Menú principal"')->and($html)->toContain('href="'.route('inicio').'"');
});

it('cada rol ve solo los grupos permitidos por sus permisos', function () {
    $nombres = fn (string $rol) => array_column(Menu::gruposPara(usuarioConRolDeSeeder($rol)), 'nombre');

    expect($nombres('Administrador'))->toBe(['Administración', 'Gestión académica', 'Matrícula', 'Asistencia', 'Evaluación'])
        ->and($nombres('Director'))->toBe(['Gestión académica', 'Matrícula', 'Asistencia', 'Evaluación'])
        ->and($nombres('Padre de Familia'))->toBe(['Matrícula', 'Asistencia', 'Evaluación', 'Asistente']);
});

it('un usuario sin permisos ve el aviso en lugar de tarjetas', function () {
    $this->actingAs(usuarioConPermisos([]))->get('/inicio')->assertOk()->assertSee('todavía no tiene módulos habilitados');
});

it('el encabezado muestra el nombre, el rol y el año escolar vigente', function () {
    $usuario = usuarioConRolDeSeeder('Docente');
    AnioEscolar::factory()->vigente()->create(['nombre' => '2026']);

    $this->actingAs($usuario)->get('/inicio')->assertOk()
        ->assertSee($usuario->nombre_completo)->assertSee('Docente')->assertSee('Año escolar 2026');
});

it('el login muestra el nombre de la institución de la base de datos, el botón de mostrar contraseña y no ofrece Recordarme', function () {
    Institucion::actual()->forceFill(['nombre' => 'Colegio Ficticio Prueba', 'codigo_modular' => '0000001', 'ugel_dre' => 'UGEL Ficticia'])->save();

    $this->get('/login')->assertOk()
        ->assertSee('Colegio Ficticio Prueba')
        ->assertSee('Mostrar la contraseña')
        ->assertDontSee('Recordarme')
        ->assertDontSee('remember');
});
