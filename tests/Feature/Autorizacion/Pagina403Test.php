<?php

use Illuminate\Support\Facades\Route;

it('muestra la página 403 en español con enlace al inicio', function () {
    Route::middleware(['web', 'auth', 'can:usuarios.ver'])->get('/_prueba-403', fn () => 'ok');

    $this->actingAs(usuarioConPermisos([]))->get('/_prueba-403')
        ->assertForbidden()
        ->assertSee('No tienes permiso para realizar esta acción.')
        ->assertSee('href="'.url('/inicio').'"', false);
});
