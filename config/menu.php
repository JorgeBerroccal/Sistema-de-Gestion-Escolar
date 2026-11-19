<?php

// Ítems del menú principal. `permiso` nulo = visible para cualquier usuario autenticado.
// `grupo` agrupa el menú lateral y el Inicio (nulo = suelto); `icono` es un Heroicon de components/icono.
// Los ítems cuya ruta no existe aún se omiten (Route::has).
return [
    ['etiqueta' => 'Inicio', 'ruta' => 'inicio', 'permiso' => null, 'grupo' => null, 'icono' => 'home', 'descripcion' => 'Resumen de tus accesos.'],
    ['etiqueta' => 'Usuarios', 'ruta' => 'admin.usuarios.index', 'permiso' => 'usuarios.ver', 'grupo' => 'Administración', 'icono' => 'users', 'descripcion' => 'Cuentas de acceso al sistema.'],
    ['etiqueta' => 'Roles', 'ruta' => 'admin.roles.index', 'permiso' => 'roles.ver', 'grupo' => 'Administración', 'icono' => 'shield-check', 'descripcion' => 'Roles y los permisos que reciben.'],
    ['etiqueta' => 'Permisos', 'ruta' => 'admin.permisos.index', 'permiso' => 'roles.ver', 'grupo' => 'Administración', 'icono' => 'key', 'descripcion' => 'Catálogo de permisos del sistema.'],
    ['etiqueta' => 'Institución', 'ruta' => 'academico.institucion', 'permiso' => 'academico.ver', 'grupo' => 'Gestión académica', 'icono' => 'building-library', 'descripcion' => 'Datos del colegio y escala de calificación.'],
    ['etiqueta' => 'Niveles', 'ruta' => 'academico.niveles.index', 'permiso' => 'academico.ver', 'grupo' => 'Gestión académica', 'icono' => 'academic-cap', 'descripcion' => 'Niveles educativos.'],
    ['etiqueta' => 'Años escolares', 'ruta' => 'academico.anios.index', 'permiso' => 'academico.ver', 'grupo' => 'Gestión académica', 'icono' => 'calendar', 'descripcion' => 'Años y periodos de evaluación.'],
    ['etiqueta' => 'Grados', 'ruta' => 'academico.grados.index', 'permiso' => 'academico.ver', 'grupo' => 'Gestión académica', 'icono' => 'rectangle-group', 'descripcion' => 'Grados de cada nivel.'],
    ['etiqueta' => 'Secciones', 'ruta' => 'academico.secciones.index', 'permiso' => 'academico.ver', 'grupo' => 'Gestión académica', 'icono' => 'squares-2x2', 'descripcion' => 'Secciones del año escolar.'],
    ['etiqueta' => 'Cursos', 'ruta' => 'academico.cursos.index', 'permiso' => 'academico.ver', 'grupo' => 'Gestión académica', 'icono' => 'book-open', 'descripcion' => 'Cursos de cada grado.'],
    ['etiqueta' => 'Asignaciones', 'ruta' => 'academico.asignaciones.index', 'permiso' => 'asignaciones.ver_todas', 'grupo' => 'Gestión académica', 'icono' => 'clipboard-document-list', 'descripcion' => 'Docentes asignados a cursos y secciones.'],
    ['etiqueta' => 'Mis asignaciones', 'ruta' => 'academico.asignaciones.propias', 'permiso' => 'asignaciones.ver_propias', 'grupo' => 'Gestión académica', 'icono' => 'clipboard-document-list', 'descripcion' => 'Tus cursos y secciones.'],
    ['etiqueta' => 'Alumnos', 'ruta' => 'matricula.alumnos.index', 'permiso' => 'matricula.ver_todas', 'grupo' => 'Matrícula', 'icono' => 'identification', 'descripcion' => 'Fichas y matrículas de los alumnos.'],
    ['etiqueta' => 'Nóminas', 'ruta' => 'matricula.nominas.index', 'permiso' => 'matricula.ver_todas', 'grupo' => 'Matrícula', 'icono' => 'user-group', 'descripcion' => 'Alumnos matriculados por sección.'],
    ['etiqueta' => 'Mis secciones', 'ruta' => 'matricula.nominas.index', 'permiso' => 'matricula.ver_nomina_propia', 'grupo' => 'Matrícula', 'icono' => 'user-group', 'descripcion' => 'Nóminas de tus secciones.'],
    // El texto depende del permiso de vínculo, no del rol (SC-005): el apoderado ve a sus estudiantes; el alumno, su ficha.
    ['etiqueta' => 'Estudiantes a mi cargo', 'ruta' => 'matricula.propios.index', 'permiso' => 'matricula.ser_apoderado', 'grupo' => 'Matrícula', 'icono' => 'identification', 'descripcion' => 'Ficha y matrícula de tus estudiantes.'],
    ['etiqueta' => 'Mi ficha', 'ruta' => 'matricula.propios.index', 'permiso' => 'matricula.ser_alumno', 'grupo' => 'Matrícula', 'icono' => 'identification', 'descripcion' => 'Tus datos y tu matrícula vigente.'],
    ['etiqueta' => 'Tomar asistencia', 'ruta' => 'asistencia.tomar.index', 'permiso' => 'asistencia.gestionar', 'grupo' => 'Asistencia', 'icono' => 'clipboard-document-check', 'descripcion' => 'Registrar la asistencia de cualquier sección.'],
    ['etiqueta' => 'Asistencia de mis secciones', 'ruta' => 'asistencia.tomar.index', 'permiso' => 'asistencia.registrar_propia', 'grupo' => 'Asistencia', 'icono' => 'clipboard-document-check', 'descripcion' => 'Registrar la asistencia de tus secciones.'],
    ['etiqueta' => 'Reportes de asistencia', 'ruta' => 'asistencia.reportes.index', 'permiso' => 'asistencia.ver_todas', 'grupo' => 'Asistencia', 'icono' => 'chart-bar', 'descripcion' => 'Reportes de asistencia por sección.'],
    ['etiqueta' => 'Reportes de asistencia de mis secciones', 'ruta' => 'asistencia.reportes.index', 'permiso' => 'asistencia.registrar_propia', 'grupo' => 'Asistencia', 'icono' => 'chart-bar', 'descripcion' => 'Reportes de tus secciones.'],
    ['etiqueta' => 'Mi asistencia', 'ruta' => 'asistencia.propia.index', 'permiso' => 'asistencia.ver_propia', 'grupo' => 'Asistencia', 'icono' => 'calendar-days', 'descripcion' => 'Tus asistencias y faltas.'],
    ['etiqueta' => 'Registro de notas', 'ruta' => 'notas.registro.index', 'permiso' => 'notas.gestionar', 'grupo' => 'Evaluación', 'icono' => 'pencil-square', 'descripcion' => 'Criterios y notas de cualquier sección.'],
    ['etiqueta' => 'Notas de mis cursos', 'ruta' => 'notas.registro.index', 'permiso' => 'notas.registrar_propia', 'grupo' => 'Evaluación', 'icono' => 'pencil-square', 'descripcion' => 'Criterios y notas de tus cursos.'],
    ['etiqueta' => 'Cierres de periodo', 'ruta' => 'notas.cierres.index', 'permiso' => 'notas.cerrar_periodo', 'grupo' => 'Evaluación', 'icono' => 'lock-closed', 'descripcion' => 'Cerrar y reabrir periodos.'],
    ['etiqueta' => 'Reportes de notas', 'ruta' => 'notas.reportes.index', 'permiso' => 'notas.ver_todas', 'grupo' => 'Evaluación', 'icono' => 'presentation-chart-line', 'descripcion' => 'Reportes de notas por sección.'],
    ['etiqueta' => 'Reportes de notas de mis cursos', 'ruta' => 'notas.reportes.index', 'permiso' => 'notas.registrar_propia', 'grupo' => 'Evaluación', 'icono' => 'presentation-chart-line', 'descripcion' => 'Reportes de tus cursos.'],
    ['etiqueta' => 'Mis notas', 'ruta' => 'notas.propia.index', 'permiso' => 'notas.ver_propia', 'grupo' => 'Evaluación', 'icono' => 'document-text', 'descripcion' => 'Notas y boleta de los periodos publicados.'],
    ['etiqueta' => 'Asistente virtual', 'ruta' => 'asistente.index', 'permiso' => 'asistente.usar', 'grupo' => 'Asistente', 'icono' => 'chat-bubble-left-right', 'descripcion' => 'Consulta notas, asistencia y matrícula.'],
];
