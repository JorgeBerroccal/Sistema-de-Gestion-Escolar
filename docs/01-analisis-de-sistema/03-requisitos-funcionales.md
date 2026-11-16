# 03 - Requisitos funcionales

> **Historia de usuario:** expresa la necesidad desde el punto de vista del usuario.
> **Requisito funcional:** expresa lo que el sistema debe hacer para satisfacer esa necesidad.

La columna **Estado real** indica lo construido y su respaldo. Estados: **Implementado**, **Modificado**, **Parcial**, **No implementado**. Las siglas FR y US se refieren a la spec indicada de `specs/`.

## Requisitos funcionales

| ID | Módulo | Requisito funcional | Estado real |
|----|--------|---------------------|-------------|
| RF01 | M1 | El sistema debe permitir el inicio y cierre de sesión mediante usuario y contraseña. | **Implementado.** Spec 001, FR-001 a FR-008. Login propio (`app/Http/Controllers/Auth/LoginController.php`), con correo o usuario, límite de 5 intentos y sesión de 120 minutos. |
| RF02 | M1 | El sistema debe restringir el acceso a cada funcionalidad según los permisos del rol del usuario (RBAC). | **Implementado.** Spec 001, FR-011 a FR-013. `Gate::before` consulta `rol_permiso`; las rutas usan `can:` y las pantallas con pertenencia comprueban en `mount()`. 31 permisos en `RolesYPermisosSeeder`. |
| RF03 | M1 | El sistema debe permitir crear, editar y desactivar cuentas de usuario y asignarles un rol. | **Implementado.** Spec 001, FR-016 a FR-018, FR-022. También reactiva cuentas y restablece contraseñas; no elimina. |
| RF04 | M1 | El sistema debe permitir crear roles y asignar o quitar permisos a cada rol sin modificar el código. | **Parcial.** Se asignan y quitan permisos de los cinco roles y se crean o editan permisos (spec 001, FR-019 y FR-020). **Crear roles desde la interfaz no está implementado**: la spec lo deja fuera de alcance. |
| RF05 | M2 | El sistema debe permitir configurar los datos de la institución: nombre, logo, niveles que atiende, año escolar vigente, periodos de evaluación y escala de calificación (literal o vigesimal). | **Parcial.** Spec 003, FR-001 a FR-008: nombre, código modular, UGEL/DRE, niveles, escala, años (uno vigente) y periodos (bimestres o trimestres). **El logo no existe** (ver «Cómo se verificó»). |
| RF06 | M2 | El sistema debe permitir registrar, editar y desactivar grados y secciones por nivel educativo. | **Implementado.** Spec 003, FR-009, FR-010, FR-012, FR-013. |
| RF07 | M2 | El sistema debe permitir registrar cursos y asociarlos a un grado y nivel. | **Implementado.** Spec 003, FR-011: un curso tiene un nivel y uno o más grados. |
| RF08 | M2 | El sistema debe permitir asignar un docente a un curso dentro de una sección. | **Implementado.** Spec 003, FR-014 a FR-019: un docente por curso en cada sección del año vigente; varias asignaciones en una sola operación. |
| RF09 | M3 | El sistema debe permitir registrar estudiantes y matricularlos en un grado y sección del año escolar vigente. | **Implementado.** Spec 004, FR-001 a FR-006, FR-011 a FR-013: ficha con documento único por tipo y número; matrícula individual y masiva (todo o nada); una matrícula activa por alumno y año. |
| RF10 | M3 | El sistema debe permitir registrar los datos del apoderado y vincularlo con uno o más estudiantes. | **Implementado.** Spec 004, FR-007 a FR-010: el apoderado es un usuario con el permiso `matricula.ser_apoderado`. |
| RF11 | M3 | El sistema debe permitir trasladar a un estudiante entre secciones conservando su historial académico. | **Implementado** por estados. Spec 004, FR-014 a FR-016: «retirado» o «trasladado» y nueva matrícula; el historial se conserva. |
| RF12 | M4 | El sistema debe permitir al docente registrar la asistencia diaria por sección y curso asignado. | **Modificado.** Spec 005, FR-001 a FR-008: asistencia **diaria por sección** y fecha, para el Docente con una asignación en la sección; no se registra por curso. |
| RF13 | M4 | El sistema debe clasificar la asistencia en: asistió, tardanza, falta justificada y falta injustificada. | **Modificado.** Spec 005, FR-002 y R-01: catálogo en la tabla `estados_asistencia` con presente, tarde, falta y falta justificada; la justificación es un cambio de estado con motivo. |
| RF14 | M4 | El sistema debe generar reportes de asistencia por estudiante, sección o grado. | **Parcial.** Spec 005, FR-011 y FR-012: por sección (rango o periodo) y por alumno; **por grado no está implementado**. |
| RF15 | M5 | El sistema debe permitir al docente registrar calificaciones por competencia, validando que estén dentro de la escala configurada. | **Modificado.** Spec 006, FR-001 a FR-006: criterios con peso por curso, sección y periodo; cada nota se valida contra la escala de la institución. No hay competencias del Currículo Nacional. |
| RF16 | M5 | El sistema debe calcular automáticamente el promedio del estudiante por curso y periodo. | **Implementado.** Spec 006, FR-007 y R-04: promedio ponderado por periodo y promedio anual, calculados al leer. |
| RF17 | M5 | El sistema debe generar la boleta de calificaciones en formato PDF, descargable por el padre de familia y el alumno. | **Implementado.** Spec 006, FR-011 y FR-012: PDF de un solo formato con DomPDF, generado en la solicitud (sin cola). El Docente no accede a la boleta completa. |
| RF18 | M4/M5 | El sistema debe permitir al padre de familia y al alumno consultar notas y asistencia, limitado a su propia información o la de sus hijos. | **Implementado.** Spec 005, FR-010; spec 006, FR-010. Filtro de pertenencia (ADR-011); las notas solo de periodos publicados. |
| RF19 | M6 | El sistema debe ofrecer un asistente virtual que responda en lenguaje natural consultas sobre notas, asistencia y horarios. | **Parcial.** Spec 007, FR-002: notas y promedios, asistencia, matrícula y sección. **Horarios: no implementado**, el sistema no los modela (ver «Cómo se verificó»). |
| RF20 | M6 | El sistema debe enviar al servicio de IA únicamente los datos que el usuario autenticado tiene permiso de ver según su rol. | **Implementado.** Spec 007, FR-004 a FR-008 y R-06, R-07, R-13. Contexto armado en el servidor, de un solo alumno propio, sin identificadores personales; probado sobre el contenido enviado al proveedor. |
| RF21 | Transversal | El sistema debe generar reportes consolidados de matrícula, asistencia y rendimiento para el director. | **Parcial.** El Director consulta nóminas por sección (spec 004), el reporte de asistencia por sección (spec 005) y el reporte de notas por sección y periodo (spec 006). **No existe un reporte consolidado** de toda la institución. |
| RF22 | Transversal | El sistema debe registrar en una bitácora de auditoría los inicios de sesión, los registros/modificaciones de notas y la gestión de usuarios. | **Parcial.** Solo `registrado_por` en `notas` y `asistencias` y `cerrado_por`/`cerrado_en` en `cierres_periodo`. **No hay bitácora**: ni inicios de sesión, ni gestión de usuarios, ni valores anteriores. |

## Relación entre historias de usuario y requisitos funcionales

| Historia de usuario | Requisitos funcionales relacionados |
|---------------------|-------------------------------------|
| HU01 Iniciar sesión | RF01, RF02 |
| HU02 Gestionar usuarios | RF03 |
| HU03 Configurar permisos por rol | RF04 |
| HU04 Configurar institución | RF05 |
| HU05 Registrar grados, secciones y cursos | RF06, RF07 |
| HU06 Asignar docentes | RF08 |
| HU07 Matricular estudiante | RF09, RF10 |
| HU08 Trasladar estudiante | RF11 |
| HU09 Registrar asistencia | RF12, RF13 |
| HU10 Reportes de asistencia | RF14 |
| HU11 Registrar calificaciones | RF15 |
| HU12 Calcular promedios | RF16 |
| HU13 Descargar boleta PDF | RF17 |
| HU14 Consultar notas y asistencia (alumno) | RF18 |
| HU15 Consultar al asistente virtual | RF19 |
| HU16 Privacidad en el asistente virtual | RF02, RF20 |
| HU17 Reportes consolidados | RF21 |
| HU18 Auditoría | RF22 |

## Resumen del estado real

| Estado | Requisitos |
|--------|------------|
| Implementado | RF01, RF02, RF03, RF06, RF07, RF08, RF09, RF10, RF11, RF16, RF17, RF18, RF20 |
| Modificado | RF12, RF13, RF15 |
| Parcial | RF04, RF05, RF14, RF19, RF21, RF22 |
| No implementado | Ninguno completo; las partes faltantes están dentro de los parciales |

## Cómo se verificó

Los puntos que el análisis dejó abiertos se comprobaron en el código del repositorio:

| Punto | Comprobación | Resultado |
|-------|--------------|-----------|
| Logo en RF05 | Búsqueda de `logo` (sin distinguir mayúsculas) en `app/`, `database/`, `resources/` y `config/`, y lectura de la migración `2026_10_24_000002_create_instituciones_table.php` | Solo aparecen coincidencias con `logout`. La tabla `instituciones` tiene `nombre`, `codigo_modular`, `ugel_dre` y `escala_calificacion_id`. No hay logo. |
| Horarios en RF19 | Búsqueda de `horario` en `app/`, `database/`, `resources/`, `config/`, `routes/` y `tests/` | Sin coincidencias: no hay tabla, modelo ni pantalla de horarios, y el asistente no puede responderlos. |
| Crear roles en RF04 | Lectura de `app/Livewire/Admin/Roles/` y de `specs/001-auth-roles-rbac/spec.md` (supuestos) | `Roles\Index` solo lista y `Roles\Permisos` asigna permisos; la spec deja crear o eliminar roles fuera de alcance. |
| Reporte consolidado en RF21 | Lectura de `routes/web.php`, `Livewire/Asistencia/Reportes`, `Livewire/Evaluacion/Reportes` y `Livewire/Matricula/Nominas` | Todos los reportes se piden por sección; no hay una ruta ni componente agregado por institución o grado. |
| Auditoría en RF22 | Búsqueda de `audit`, `bitacora`, `registrado_por`, `cerrado_por` en `app/` y `database/migrations/` | Solo `registrado_por` (`notas`, `asistencias`) y `cerrado_por` (`cierres_periodo`). No hay tabla de bitácora, eventos ni listeners. |
