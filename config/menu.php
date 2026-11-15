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
    ['etiqueta' => 'Grados', 'ruta' => 'academico.grados.index', 'permiso' => 'academico.ver'],
    ['etiqueta' => 'Secciones', 'ruta' => 'academico.secciones.index', 'permiso' => 'academico.ver'],
    ['etiqueta' => 'Cursos', 'ruta' => 'academico.cursos.index', 'permiso' => 'academico.ver'],
    ['etiqueta' => 'Asignaciones', 'ruta' => 'academico.asignaciones.index', 'permiso' => 'asignaciones.ver_todas'],
    ['etiqueta' => 'Mis asignaciones', 'ruta' => 'academico.asignaciones.propias', 'permiso' => 'asignaciones.ver_propias'],
    ['etiqueta' => 'Alumnos', 'ruta' => 'matricula.alumnos.index', 'permiso' => 'matricula.ver_todas'],
    ['etiqueta' => 'Nóminas', 'ruta' => 'matricula.nominas.index', 'permiso' => 'matricula.ver_todas'],
    ['etiqueta' => 'Mis secciones', 'ruta' => 'matricula.nominas.index', 'permiso' => 'matricula.ver_nomina_propia'],
    // El texto depende del permiso de vínculo, no del rol (SC-005): el apoderado ve a sus estudiantes; el alumno, su ficha.
    ['etiqueta' => 'Estudiantes a mi cargo', 'ruta' => 'matricula.propios.index', 'permiso' => 'matricula.ser_apoderado'],
    ['etiqueta' => 'Mi ficha', 'ruta' => 'matricula.propios.index', 'permiso' => 'matricula.ser_alumno'],
    ['etiqueta' => 'Tomar asistencia', 'ruta' => 'asistencia.tomar.index', 'permiso' => 'asistencia.gestionar'],
    ['etiqueta' => 'Asistencia de mis secciones', 'ruta' => 'asistencia.tomar.index', 'permiso' => 'asistencia.registrar_propia'],
    ['etiqueta' => 'Reportes de asistencia', 'ruta' => 'asistencia.reportes.index', 'permiso' => 'asistencia.ver_todas'],
    ['etiqueta' => 'Reportes de asistencia de mis secciones', 'ruta' => 'asistencia.reportes.index', 'permiso' => 'asistencia.registrar_propia'],
    ['etiqueta' => 'Mi asistencia', 'ruta' => 'asistencia.propia.index', 'permiso' => 'asistencia.ver_propia'],
    ['etiqueta' => 'Registro de notas', 'ruta' => 'notas.registro.index', 'permiso' => 'notas.gestionar'],
    ['etiqueta' => 'Notas de mis cursos', 'ruta' => 'notas.registro.index', 'permiso' => 'notas.registrar_propia'],
    ['etiqueta' => 'Cierres de periodo', 'ruta' => 'notas.cierres.index', 'permiso' => 'notas.cerrar_periodo'],
    ['etiqueta' => 'Reportes de notas', 'ruta' => 'notas.reportes.index', 'permiso' => 'notas.ver_todas'],
    ['etiqueta' => 'Reportes de notas de mis cursos', 'ruta' => 'notas.reportes.index', 'permiso' => 'notas.registrar_propia'],
    ['etiqueta' => 'Mis notas', 'ruta' => 'notas.propia.index', 'permiso' => 'notas.ver_propia'],
    ['etiqueta' => 'Asistente virtual', 'ruta' => 'asistente.index', 'permiso' => 'asistente.usar'],
];
