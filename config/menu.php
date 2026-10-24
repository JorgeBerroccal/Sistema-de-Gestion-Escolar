<?php

// Ítems del menú principal. `permiso` nulo = visible para cualquier usuario autenticado.
// Los ítems cuya ruta no existe aún se omiten (Route::has).
return [
    ['etiqueta' => 'Inicio', 'ruta' => 'inicio', 'permiso' => null],
    ['etiqueta' => 'Usuarios', 'ruta' => 'admin.usuarios.index', 'permiso' => 'usuarios.ver'],
    ['etiqueta' => 'Roles', 'ruta' => 'admin.roles.index', 'permiso' => 'roles.ver'],
    ['etiqueta' => 'Permisos', 'ruta' => 'admin.permisos.index', 'permiso' => 'roles.ver'],
    ['etiqueta' => 'Institución', 'ruta' => 'academico.institucion', 'permiso' => 'academico.ver'],
    ['etiqueta' => 'Niveles', 'ruta' => 'academico.niveles.index', 'permiso' => 'academico.ver'],
    ['etiqueta' => 'Años escolares', 'ruta' => 'academico.anios.index', 'permiso' => 'academico.ver'],
];
