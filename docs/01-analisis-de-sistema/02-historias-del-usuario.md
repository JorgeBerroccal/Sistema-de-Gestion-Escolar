# 02 - Historias de usuario

> **Formato:** Como [actor], quiero [acción], para [beneficio].

La columna **Estado real** indica qué se construyó y dónde está respaldado (spec o evidencia). Estados: **Implementada**, **Modificada**, **Parcial**, **No implementada**.

## Historias de usuario por módulo

| ID | Módulo | Historia de usuario | Estado real |
|----|--------|---------------------|-------------|
| HU01 | M1 Autenticación y Roles | Como usuario del sistema, quiero iniciar sesión con mi usuario y contraseña, para acceder solo a las funciones que corresponden a mi rol. | **Implementada.** Spec 001, US1 (FR-001 a FR-008): acceso con correo o nombre de usuario, límite de intentos, cierre de sesión por inactividad (120 min). |
| HU02 | M1 Autenticación y Roles | Como administrador, quiero crear, editar y desactivar cuentas de usuario asignándoles un rol, para controlar quién accede al sistema. | **Implementada.** Spec 001, US3 (FR-016 a FR-018, FR-022). Las cuentas no se eliminan: se desactivan y se reactivan. |
| HU03 | M1 Autenticación y Roles | Como administrador, quiero asignar permisos a cada rol de forma configurable, para modificar los accesos sin tener que cambiar el código fuente. | **Implementada.** Spec 001, US4 (FR-019, FR-020). Se asignan permisos a los cinco roles y se crean o editan permisos; crear o eliminar roles queda fuera de alcance. |
| HU04 | M2 Gestión Académica Base | Como administrador, quiero configurar los datos generales de la institución (nombre, niveles, año escolar, periodos y escala de calificación), para adaptar el sistema a mi colegio. | **Implementada** con una diferencia. Spec 003, US1: ficha única (nombre, código modular, UGEL/DRE), niveles, escala, años y periodos (bimestres o trimestres). No hay logo. |
| HU05 | M2 Gestión Académica Base | Como administrador, quiero registrar grados, secciones y cursos por nivel educativo, para reflejar la estructura académica real de la institución. | **Implementada.** Spec 003, US2 y US5 (se desactiva en lugar de eliminar cuando hay asignaciones o matrículas). |
| HU06 | M2 Gestión Académica Base | Como administrador, quiero asignar docentes a cursos dentro de una sección, para definir quién dicta cada curso. | **Implementada.** Spec 003, US3: un docente por curso en cada sección del año vigente. |
| HU07 | M3 Matrícula | Como administrador, quiero registrar a un estudiante con los datos de su apoderado y matricularlo en un grado y sección del año vigente, para formalizar su inscripción. | **Implementada.** Spec 004, US1 y US3: ficha con documento único por tipo y número, vínculo con apoderados y con el usuario del alumno, matrícula individual y masiva. |
| HU08 | M3 Matrícula | Como administrador, quiero trasladar a un estudiante de una sección a otra, para reubicarlo sin perder su historial académico. | **Implementada** por estados. Spec 004, US2 (FR-014 a FR-016): la matrícula pasa a «retirado» o «trasladado» y se matricula de nuevo; el historial se conserva. |
| HU09 | M4 Asistencia | Como docente, quiero registrar la asistencia diaria de mis estudiantes (asistió, tardanza, falta justificada o injustificada), para llevar el control de asistencia de mis cursos. | **Modificada.** Spec 005, US1 y US3: la asistencia es **por sección y fecha**, no por curso. Estados (tabla): presente, tarde, falta y falta justificada; justificar es cambiar el estado con un motivo. |
| HU10 | M4 Asistencia | Como director, quiero consultar reportes de asistencia por estudiante, sección o grado, para detectar problemas de inasistencia a tiempo. | **Parcial.** Spec 005, US4 (FR-012): reporte por sección y rango o periodo, con ranking de faltas injustificadas, y detalle por alumno. No hay reporte por grado. |
| HU11 | M5 Evaluación y Boletas | Como docente, quiero registrar las calificaciones de mis estudiantes por competencia, para evaluar su progreso según la escala de la institución. | **Modificada.** Spec 006, US1: el Docente define **criterios de evaluación con peso** (suman 100 %) por curso, sección y periodo y registra una nota por alumno y criterio. No se modelan las competencias del Currículo Nacional. |
| HU12 | M5 Evaluación y Boletas | Como docente, quiero que el sistema calcule automáticamente el promedio por curso y periodo, para evitar errores de cálculo manual. | **Implementada.** Spec 006, FR-007 y R-04: promedio ponderado calculado al leer (no se guarda) y promedio anual. |
| HU13 | M5 Evaluación y Boletas | Como padre de familia, quiero descargar la boleta de calificaciones de mi hijo en PDF, para conocer su rendimiento académico. | **Implementada.** Spec 006, US4: PDF de un solo formato, por periodo o anual, de periodos publicados; el periodo sin cerrar sale como «BORRADOR» solo para Administrador y Director. |
| HU14 | M4 / M5 Consulta | Como alumno, quiero consultar mis notas y mi asistencia, para hacer seguimiento a mi propio desempeño. | **Implementada.** Spec 005, US2 y spec 006, US2. |
| HU15 | M6 Asistente Virtual | Como padre de familia, quiero preguntarle al asistente virtual por las notas, faltas u horario de mi hijo, para obtener la información rápidamente sin navegar por varias pantallas. | **Parcial.** Spec 007, US1: responde sobre notas y promedios, asistencia, matrícula y sección. El **horario** no existe en el sistema. |
| HU16 | M6 Asistente Virtual | Como padre de familia, quiero que el asistente virtual solo me muestre información de mis propios hijos, para que los datos de los estudiantes estén protegidos. | **Implementada.** Spec 007, US2: el servidor elige al alumno entre los propios y el contexto contiene a un solo alumno, sin documento, fecha de nacimiento ni correo. |
| HU17 | Transversal | Como director, quiero consultar reportes consolidados de matrícula y rendimiento académico, para tomar decisiones de gestión. | **Parcial.** Hay reporte de notas por sección y periodo, con orden de mérito y desaprobados por curso (spec 006, US5), y nóminas por sección. No hay consolidado de toda la institución. |
| HU18 | Transversal | Como administrador, quiero que el sistema registre las acciones críticas (inicios de sesión, cambios de notas, gestión de usuarios), para poder auditar quién hizo cada cambio. | **Parcial.** Solo `registrado_por` en `notas` y `asistencias` y `cerrado_por` en `cierres_periodo`. No hay bitácora de inicios de sesión ni de gestión de usuarios, ni valor anterior (ADR-010 no implementada). |

## Resumen por actor

| Actor | Historia | Estado real |
|-------|----------|-------------|
| Administrador | HU02 | Implementada |
| Administrador | HU03 | Implementada |
| Administrador | HU04 | Implementada (sin logo) |
| Administrador | HU05 | Implementada |
| Administrador | HU06 | Implementada |
| Administrador | HU07 | Implementada |
| Administrador | HU08 | Implementada (por estados) |
| Administrador | HU18 | **Parcial** |
| Director | HU10 | **Parcial** |
| Director | HU17 | **Parcial** |
| Docente | HU09 | **Modificada** |
| Docente | HU11 | **Modificada** |
| Docente | HU12 | Implementada |
| Padre de Familia | HU13 | Implementada |
| Padre de Familia | HU15 | **Parcial** |
| Padre de Familia | HU16 | Implementada |
| Alumno | HU14 | Implementada |
| Todos los usuarios | HU01 | Implementada |

Total: 11 implementadas, 2 modificadas y 5 parciales; ninguna sin implementar por completo.

## Capacidades construidas que el análisis original no listó como historia

Salen de las specs del repositorio y no tienen ID de HU en el laboratorio:

| Capacidad | Origen |
|-----------|--------|
| Cierre y reapertura de periodos por sección, que bloquea al Docente y **publica** notas y boleta a las familias. | Spec 006, US3 |
| Reporte de notas por sección con orden de mérito y desaprobados por curso. | Spec 006, US5 |
| Justificar y revertir la justificación de una falta. | Spec 005, US3 |
| Vínculos del alumno con su usuario y con uno o más apoderados. | Spec 004, US3 |
| Desactivar en lugar de eliminar elementos con historial. | Spec 003, US5 |
| Proveedor de IA intercambiable, límite de solicitudes y falla cerrado en producción. | Spec 007, US4 y US5 |
| Mecanismo de pertenencia fail-closed para los datos propios. | Spec 001, US6 (ADR-011) |
| Despliegue en staging, integración continua, respaldo y restauración. | Spec 002 |
