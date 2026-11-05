# Feature Specification: Asistencia

**Feature Branch**: `005-asistencia`

**Created**: 2026-11-05

**Status**: Draft

**Módulo MVP**: 4 — Asistencia

**Input**: User description: "Módulo 4: Asistencia (rama 005-asistencia, release v0.5.0). Registro de asistencia diaria por sección/curso por parte de docentes, consulta por alumnos/apoderados y reportes básicos, siguiendo el estilo de los módulos anteriores (RBAC, matrícula, datos ficticios)."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Tomar la asistencia diaria de una sección (Priority: P1)

Un Docente con asignación en una sección del año vigente abre la asistencia de un día, ve la nómina de
alumnos con matrícula activa y marca a cada uno como presente, tarde, falta o falta justificada (con
observación opcional). Guarda toda la lista en una sola operación. Si el día ya tenía registro, lo reabre
y lo corrige.

**Why this priority**: Es el núcleo del módulo; sin el registro diario no hay nada que consultar ni
reportar.

**Independent Test**: Con un Docente asignado a una sección con cinco alumnos matriculados, registrar la
asistencia de hoy (uno con falta, uno tarde), guardar, reabrir y comprobar que los estados persisten; cambiar
uno y verificar la corrección.

**Acceptance Scenarios**:

1. **Given** un Docente con asignación en una sección del año vigente, **When** abre la asistencia de una
   fecha, **Then** ve a los alumnos con matrícula activa en esa sección, todos preseleccionados como
   «presente».
2. **Given** la lista abierta, **When** el Docente marca estados y guarda, **Then** queda un registro por
   alumno y fecha con su estado y observación opcional.
3. **Given** una fecha ya registrada, **When** el Docente la abre de nuevo y cambia estados, **Then** los
   cambios reemplazan al registro previo sin duplicarlo.
4. **Given** una fecha futura, **When** el Docente intenta registrarla, **Then** el sistema lo rechaza.
5. **Given** un Docente sin asignación en la sección, **When** intenta abrir o guardar su asistencia,
   **Then** el sistema deniega el acceso.
6. **Given** un alumno retirado o trasladado, **When** se abre una fecha posterior a su retiro, **Then** no
   aparece en la lista; en fechas anteriores a su retiro sí aparece.
7. **Given** una fecha que cae en sábado o domingo, **When** el Docente intenta abrirla para registrar,
   **Then** el sistema avisa que no se registra asistencia en fin de semana y no permite guardarla.

---

### User Story 2 - Consultar la asistencia propia y la de los hijos (Priority: P1)

El Alumno ve su propia asistencia y el Padre de Familia la de cada uno de sus hijos: calendario o lista
por mes con el estado de cada día, y un resumen con totales de presentes, tardanzas, faltas y faltas
justificadas, y porcentaje de asistencia.

**Why this priority**: Es el valor para las familias y alimenta la boleta y el chatbot de módulos
posteriores.

**Independent Test**: Con un Padre con dos hijos y asistencia registrada, comprobar que ve ambos con sus
resúmenes y no ve a ningún otro alumno; con un Alumno, que solo ve la suya.

**Acceptance Scenarios**:

1. **Given** un Padre de Familia con hijos vinculados, **When** abre la asistencia, **Then** elige entre sus
   hijos y ve el detalle por mes y el resumen del año.
2. **Given** un Alumno con usuario vinculado, **When** abre la asistencia, **Then** ve solo la suya.
3. **Given** un Padre o Alumno, **When** intenta acceder a la asistencia de un alumno ajeno (por dirección
   directa), **Then** el sistema deniega el acceso.
4. **Given** días sin registro, **When** el resumen se calcula, **Then** esos días no cuentan como asistidos
   ni como faltas.

---

### User Story 3 - Justificar faltas (Priority: P2)

El Administrador o el Docente de la sección convierten una falta en «falta justificada» registrando el
motivo, y pueden revertirla. Padre y Alumno ven el estado resultante, sin poder modificarlo.

**Why this priority**: Evita que un error o un certificado médico penalicen al alumno, pero el módulo
funciona sin esto.

**Independent Test**: Registrar una falta, justificarla con motivo, comprobar el resumen del alumno
(deja de contar como falta injustificada) y revertirla.

**Acceptance Scenarios**:

1. **Given** una falta registrada, **When** el Docente de la sección la justifica con un motivo, **Then** el
   estado pasa a «falta justificada» y se conserva el motivo.
2. **Given** un registro que no es falta, **When** se intenta justificar, **Then** el sistema lo rechaza.
3. **Given** un Padre o Alumno, **When** intenta justificar o editar asistencia, **Then** el sistema
   deniega la acción y los controles no se muestran.

---

### User Story 4 - Reportes de asistencia por sección y por alumno (Priority: P2)

El Administrador y el Director consultan, para una sección y un rango de fechas o periodo de evaluación, el
porcentaje de asistencia, el conteo por estado y el ranking de alumnos con más faltas. El Docente accede a
lo mismo para sus secciones. Se puede consultar el detalle por alumno.

**Why this priority**: Da visibilidad a la dirección y permite actuar sobre el ausentismo; es la parte
recortable si hay retraso.

**Independent Test**: Con asistencia de dos secciones durante varias fechas, generar el reporte de una
sección por rango y comprobar que los conteos y porcentajes coinciden con los registros.

**Acceptance Scenarios**:

1. **Given** un Director, **When** abre el reporte de una sección con un rango de fechas, **Then** ve
   totales por estado, porcentaje y alumnos ordenados por faltas, sin controles de edición.
2. **Given** un Docente, **When** abre el reporte, **Then** solo puede elegir secciones donde tiene
   asignación.
3. **Given** un rango sin registros, **When** se genera el reporte, **Then** se muestra un mensaje de «sin
   datos», no ceros engañosos.
4. **Given** el reporte de una sección, **When** se abre el detalle de un alumno, **Then** se ven sus días y
   estados del rango.

---

### Edge Cases

- Un alumno matriculado a mitad de año solo aparece desde su fecha de matrícula.
- Dos docentes de la misma sección guardan la misma fecha casi a la vez: prevalece el último guardado, sin
  duplicados.
- Una sección sin alumnos matriculados: el sistema indica que no hay nómina y no permite guardar.
- Una asignación docente desactivada o un año escolar que ya no es el vigente: el acceso de edición se
  deniega; la consulta se conserva.
- Un alumno desactivado conserva su historial de asistencia.
- Una matrícula corregida a retirada con fecha anterior a asistencias ya registradas: las asistencias
  previas se conservan; las posteriores dejan de contarse.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El sistema MUST permitir registrar la asistencia de una sección por fecha, con un estado por
  alumno: presente, tarde, falta o falta justificada, y una observación opcional.
- **FR-002**: Los estados de asistencia MUST ser un catálogo en tabla (no codificado en el código), con
  indicación de cuáles cuentan como asistencia y cuáles como falta.
- **FR-003**: El sistema MUST garantizar un único registro por alumno, sección y fecha; guardar de nuevo
  reemplaza.
- **FR-004**: La lista de la fecha MUST incluir solo a los alumnos con matrícula activa en la sección en
  esa fecha.
- **FR-005**: El sistema MUST rechazar fechas futuras (el «hoy» se calcula en la zona horaria de la
  institución, tomada de la configuración y no de la zona global de la aplicación), fechas fuera del año escolar de la sección y los
  sábados y domingos. La regla es fija y simple: no depende de calendario ni de configuración.
- **FR-006**: El guardado de la lista de una fecha MUST ser todo o nada.
- **FR-007**: El Docente MUST poder registrar y corregir asistencia solo de las secciones donde tiene una
  asignación activa del año vigente.
- **FR-008**: El Administrador MUST poder registrar y corregir asistencia de cualquier sección del año
  vigente.
- **FR-009**: El sistema MUST permitir justificar una falta con motivo y revertir la justificación; solo
  Administrador y Docente de la sección.
- **FR-010**: El Alumno MUST ver solo su asistencia y el Padre de Familia solo la de sus hijos vinculados,
  con detalle mensual y resumen (conteos por estado y porcentaje de asistencia), sin poder modificarla.
- **FR-011**: El porcentaje de asistencia MUST calcularse sobre los días computables: (presentes +
  tardanzas) / (presentes + tardanzas + faltas injustificadas). Las faltas justificadas y los días sin
  registro no entran en el denominador.
- **FR-012**: El Administrador y el Director MUST poder generar reportes por sección y rango de fechas o
  periodo de evaluación, con totales por estado, porcentaje y ranking de faltas injustificadas (las justificadas no cuentan en el
  ranking); el Director solo consulta.
- **FR-013**: Toda autorización MUST basarse en permisos de la tabla de roles y permisos, más el filtro de
  pertenencia (docente a sus secciones, familia a sus hijos), con denegación por defecto.
- **FR-014**: Los listados MUST estar paginados y los reportes MUST responder sin demora perceptible con
  secciones de tamaño típico.
- **FR-015**: Los datos de alumnos y asistencia de los seeders de ejemplo y de staging MUST ser siempre
  ficticios.
- **FR-016**: Los permisos nuevos del módulo MUST cargarse por seeder y asignarse a los roles sin cambiar
  código.

### Key Entities

- **Registro de asistencia**: estado de un alumno en una fecha dentro de una sección; incluye observación,
  motivo de justificación y quién lo registró.
- **Estado de asistencia**: catálogo (presente, tarde, falta, falta justificada) con indicador de si cuenta
  como asistencia.
- **Matrícula** (módulo 3): determina qué alumnos aparecen en la lista de una fecha.
- **Asignación docente** (módulo 2): determina qué secciones puede gestionar un Docente.
- **Periodo de evaluación** (módulo 2): rango de fechas para los reportes.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: La asistencia completa de una sección (30 alumnos) se registra con una sola operación de
  guardado, verificable con prueba automática.
- **SC-002**: Un Padre de Familia encuentra el resumen de asistencia de un hijo en como máximo 3 clics desde
  el inicio.
- **SC-003**: El 100 % de los intentos de acceder a asistencia ajena (alumno, familia o sección sin
  asignación) es denegado, verificado con pruebas automáticas.
- **SC-004**: Los totales y porcentajes de los reportes coinciden en el 100 % con los registros de la
  muestra de prueba.
- **SC-005**: El reporte de una sección con un año completo de registros se muestra en menos de 3 segundos.

## Assumptions

- La asistencia es **diaria por sección**; la asistencia por curso u hora de clase queda fuera de alcance,
  porque la matrícula y la boleta se organizan por sección y el calendario es corto.
- Cualquier docente con asignación activa en la sección puede tomar la asistencia (no se define tutor).
- La zona horaria de la institución es un valor de configuración (variable de entorno, con America/Lima como
  valor por defecto); no se cambia la zona global de la aplicación.
- Solo se rechazan sábados y domingos (regla fija, sin calendario). Los feriados y días no lectivos no se
  modelan: en esos días el Docente simplemente no registra, y los días sin registro no cuentan (FR-011).
- La justificación es un cambio de estado hecho por personal; no hay carga de documentos ni solicitudes
  desde la familia.
- Depende de Autenticación y roles (1), Gestión académica (2) y Matrícula (3), ya integrados.
- Los datos de alumnos reales no se usan en staging (Ley 29733).

### Exclusiones deliberadas

Asistencia por curso, notificaciones a apoderados, carga de certificados, feriados y días no lectivos
(sin calendario ni bloqueo de esas fechas),
importación/exportación en Excel, lectura por QR o biometría, y cálculo de notas o boletas (Módulo 5).
