# Feature Specification: Evaluación y Boletas

**Feature Branch**: `006-evaluacion-boletas`

**Created**: 2026-11-10

**Status**: Draft

**Módulo MVP**: 5 — Evaluación y Boletas

**Input**: User description: "Módulo 5 — Evaluación y Boletas: registro de notas por docente (por sección, periodo y criterio de evaluación), cálculo de promedios, consulta de notas por alumno/apoderado, y generación de boletas (formato único, descargable en PDF) por alumno y periodo. Respeta RBAC existente, datos solo ficticios en staging, endpoints críticos (login, registro de notas, consulta de boletas) con pruebas de carga k6 en una sola sesión, y prueba de humo de ~10 min para el cierre v0.6.0."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Registrar notas de una sección (Priority: P1)

Un Docente con asignación en una sección y curso del año vigente elige un periodo de evaluación y define sus
criterios de evaluación (por ejemplo «Práctica», «Examen»), cada uno con un peso. Luego ve la nómina de
alumnos con matrícula activa y escribe la nota de cada alumno en cada criterio, usando la escala de
calificación de la institución (literal o vigesimal). Guarda toda la tabla en una sola operación y puede
corregirla después.

**Why this priority**: Es el núcleo del módulo; sin notas no hay promedios, consultas ni boletas.

**Independent Test**: Con un Docente asignado a un curso de una sección con cinco alumnos, crear dos criterios
con pesos que suman 100 %, registrar notas, guardar, reabrir y comprobar que persisten; corregir una nota y
verificar el reemplazo.

**Acceptance Scenarios**:

1. **Given** un Docente con asignación en un curso y sección del año vigente, **When** elige un periodo,
   **Then** ve los criterios de ese curso en ese periodo (o puede crearlos) y la nómina con matrícula activa.
2. **Given** la tabla abierta, **When** el Docente escribe notas válidas y guarda, **Then** queda una nota por
   alumno y criterio, y se muestra el promedio del periodo de cada alumno.
3. **Given** una nota fuera de la escala (p. ej. 25 en vigesimal o «Z» en literal), **When** guarda, **Then** el
   sistema rechaza el guardado completo y señala la celda inválida.
4. **Given** criterios cuyos pesos no suman 100 %, **When** el Docente intenta guardar el esquema, **Then** el
   sistema lo rechaza.
5. **Given** un Docente sin asignación en ese curso y sección, **When** intenta abrir o guardar notas, **Then**
   el sistema deniega el acceso.
6. **Given** un periodo cerrado por el Administrador, **When** el Docente intenta guardar notas, **Then** el
   sistema lo rechaza; el Administrador sí puede corregir.

---

### User Story 2 - Consultar notas y promedios propios y de los hijos (Priority: P1)

El Alumno ve sus notas y el Padre de Familia las de cada hijo: por curso y periodo, el detalle por criterio,
el promedio del periodo por curso y el promedio anual cuando los periodos están completos.

**Why this priority**: Es el valor para las familias y alimenta la boleta y el chatbot del Módulo 6.

**Independent Test**: Con un Padre con dos hijos y notas registradas, comprobar que ve ambos con sus promedios
y a ningún otro alumno; con un Alumno, que solo ve las suyas.

**Acceptance Scenarios**:

1. **Given** un Padre con hijos vinculados, **When** abre las notas, **Then** elige un hijo y ve sus cursos con
   detalle y promedio por periodo.
2. **Given** un Alumno con usuario vinculado, **When** abre las notas, **Then** ve solo las suyas.
3. **Given** un Padre o Alumno, **When** intenta acceder a las notas de un alumno ajeno por dirección directa,
   **Then** el sistema deniega el acceso.
4. **Given** un curso sin notas en un periodo, **When** se calcula el promedio, **Then** se muestra «sin notas»,
   no un cero.
5. **Given** un periodo no publicado, **When** un Padre o Alumno lo consulta, **Then** no ve sus notas (ver
   Story 3).

---

### User Story 3 - Cerrar y publicar un periodo (Priority: P2)

El Administrador cierra un periodo de evaluación para una sección: tras el cierre los Docentes ya no pueden
modificar sus notas, y las notas y boletas pasan a ser visibles para Alumnos y Padres. Puede reabrirlo para
corregir.

**Why this priority**: Evita que las familias vean notas a medio registrar y que se cambien notas ya
entregadas; el módulo funciona sin esto, pero la boleta pierde su valor oficial.

**Independent Test**: Cerrar un periodo, comprobar que el Docente no puede editar y que el Padre ahora ve las
notas; reabrir y comprobar lo contrario.

**Acceptance Scenarios**:

1. **Given** un periodo abierto con notas, **When** el Administrador lo cierra para una sección, **Then** los
   Docentes no pueden editar y Alumnos y Padres pueden consultarlo.
2. **Given** un periodo cerrado, **When** el Administrador lo reabre, **Then** los Docentes pueden editar y
   Alumnos y Padres dejan de verlo.
3. **Given** un Docente, Padre o Alumno, **When** intenta cerrar o reabrir, **Then** el sistema lo deniega.

---

### User Story 4 - Generar la boleta de notas en PDF (Priority: P2)

El Administrador o el Docente de la sección generan la boleta de un alumno para un periodo (o la anual): un
documento PDF con los datos de la institución, del alumno, sección, cursos con sus notas y promedios, y el
resumen de asistencia del periodo. El Padre y el Alumno descargan la boleta propia de periodos publicados.
Existe un único formato de boleta.

**Why this priority**: Es el entregable visible del módulo para las familias; depende de las Stories 1–3.

**Independent Test**: Con notas registradas y el periodo publicado, descargar la boleta de un alumno y
comprobar que sus cursos, notas, promedios y asistencia coinciden con los registros.

**Acceptance Scenarios**:

1. **Given** un alumno con notas en un periodo publicado, **When** el Padre descarga la boleta, **Then** recibe
   un PDF con datos del alumno, cursos, notas por criterio, promedio por curso y resumen de asistencia.
2. **Given** un alumno cuyo curso no tiene notas en el periodo, **When** se genera la boleta, **Then** ese
   curso aparece como «sin notas».
3. **Given** un Padre o Alumno, **When** intenta descargar la boleta de un alumno ajeno o de un periodo no
   publicado, **Then** el sistema deniega el acceso.
4. **Given** un Administrador, **When** genera la boleta de un periodo no publicado, **Then** la obtiene
   marcada como «borrador».

---

### User Story 5 - Reporte de notas por sección (Priority: P3)

El Administrador y el Director consultan, para una sección y periodo, los promedios por curso y por alumno,
con el orden de mérito de la sección y el conteo de alumnos desaprobados por curso. El Docente accede a lo
mismo para sus secciones.

**Why this priority**: Da visibilidad a la dirección pero no es necesario para la boleta; es recortable.

**Independent Test**: Con notas de una sección, generar el reporte y comprobar que promedios y conteos
coinciden con los registros.

**Acceptance Scenarios**:

1. **Given** un Director, **When** abre el reporte de una sección y periodo, **Then** ve promedios por curso,
   orden de mérito y desaprobados, sin controles de edición.
2. **Given** un Docente, **When** abre el reporte, **Then** solo elige secciones donde tiene asignación.
3. **Given** una sección sin notas en el periodo, **When** se abre el reporte, **Then** se muestra «sin datos».

---

### Edge Cases

- Un alumno matriculado a mitad de año aparece solo desde su matrícula; sus notas anteriores no existen y no
  cuentan como cero.
- Un alumno retirado conserva las notas ya registradas y deja de aparecer en tablas nuevas.
- Dos Docentes (o un Docente y el Administrador) guardan la misma tabla casi a la vez: prevalece el último
  guardado, sin duplicados.
- Una celda de nota vacía significa «sin nota», no cero, y se excluye del promedio; los pesos se renormalizan
  sobre los criterios con nota.
- Modificar el peso o eliminar un criterio con notas ya registradas: el promedio se recalcula; eliminar pide
  confirmación y borra sus notas.
- Una asignación docente desactivada o un año que ya no es el vigente: se deniega la edición; la consulta se
  conserva.
- Un alumno desactivado conserva su historial de notas y sus boletas.
- Escala de calificación literal: el promedio se calcula con equivalentes numéricos y se muestra en letra
  (ver Supuestos).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El sistema MUST permitir definir, por curso, sección y periodo de evaluación, criterios de
  evaluación con nombre y peso porcentual; los pesos MUST sumar 100 %.
- **FR-002**: El sistema MUST permitir registrar una nota por alumno y criterio, validada contra la escala de
  calificación configurada en la institución (literal AD/A/B/C o vigesimal 0–20); guardar de nuevo reemplaza.
- **FR-003**: La tabla de notas MUST incluir solo a los alumnos con matrícula activa en la sección.
- **FR-004**: El guardado de la tabla de notas MUST ser todo o nada.
- **FR-005**: El Docente MUST poder registrar y corregir notas solo de los cursos y secciones donde tiene una
  asignación activa del año vigente y mientras el periodo esté abierto.
- **FR-006**: El Administrador MUST poder registrar y corregir notas de cualquier sección del año vigente,
  incluso con el periodo cerrado.
- **FR-007**: El sistema MUST calcular el promedio de periodo por curso como promedio ponderado de los criterios
  con nota, y el promedio anual por curso como promedio de los promedios de periodo existentes. Sin notas, el
  promedio es «sin notas».
- **FR-008**: El umbral de aprobación MUST ser configuración de la escala de la institución, no un valor
  codificado.
- **FR-009**: El Administrador MUST poder cerrar y reabrir un periodo por sección; el cierre bloquea la edición
  de Docentes y publica notas y boleta para Alumnos y Padres.
- **FR-010**: El Alumno MUST ver solo sus notas y el Padre solo las de sus hijos vinculados, únicamente de
  periodos publicados, sin poder modificarlas.
- **FR-011**: El sistema MUST generar la boleta de un alumno por periodo y anual en un único formato PDF, con
  datos de la institución, alumno y sección, cursos con notas por criterio y promedio, y resumen de asistencia
  del periodo (Módulo 4).
- **FR-012**: La boleta de un periodo no publicado MUST estar disponible solo para Administrador y Docente de
  la sección, marcada como borrador.
- **FR-013**: El Administrador y el Director MUST poder consultar el reporte de notas por sección y periodo; el
  Director solo consulta; el Docente accede a sus secciones.
- **FR-014**: Toda autorización MUST basarse en permisos de la tabla de roles y permisos, más el filtro de
  pertenencia (docente a sus secciones, familia a sus hijos), con denegación por defecto.
- **FR-015**: Los listados MUST estar paginados; las consultas con relaciones MUST evitar consultas repetidas
  por fila.
- **FR-016**: Los endpoints críticos — login, registro de notas y consulta de boletas — MUST contar con pruebas
  de carga ejecutadas y documentadas en una única sesión de carga de este módulo, que cubre todos los
  endpoints críticos existentes (constitución, Principio V).
- **FR-017**: Los datos de alumnos y notas de los seeders de ejemplo y de staging MUST ser siempre ficticios;
  ningún seeder crea cuentas con contraseña conocida fuera de local.
- **FR-018**: Los permisos nuevos del módulo MUST cargarse por seeder y asignarse a los roles sin cambiar código.
- **FR-019**: El sistema MUST NOT codificar nombres de cursos, periodos ni criterios; todo es dato configurable
  (Principio II).

### Key Entities

- **Criterio de evaluación**: componente de la nota de un curso en una sección y periodo; tiene nombre y peso.
- **Nota**: valor de un alumno en un criterio, expresado en la escala de la institución.
- **Cierre de periodo**: estado abierto/cerrado de un periodo para una sección; determina edición y publicación.
- **Boleta**: documento PDF derivado de notas, promedios y asistencia de un alumno en un periodo o en el año.
- **Escala de calificación** (módulo 2): define valores válidos y umbral de aprobación.
- **Periodo de evaluación**, **Asignación docente** (módulo 2), **Matrícula** (módulo 3) y **Registro de
  asistencia** (módulo 4): insumos de acceso, nómina y resumen de asistencia.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Las notas completas de una sección (30 alumnos, hasta 5 criterios) se registran con una sola
  operación de guardado, verificable con prueba automática.
- **SC-002**: Un Padre de Familia llega a la boleta de un hijo en como máximo 3 clics desde el inicio.
- **SC-003**: El 100 % de los intentos de acceder a notas o boletas ajenas, o de editar sin asignación, con
  periodo cerrado o sin permiso, es denegado, verificado con pruebas automáticas.
- **SC-004**: Los promedios de periodo, anuales y de reportes coinciden en el 100 % con el cálculo de la
  muestra de prueba, incluyendo casos de notas vacías y escala literal.
- **SC-005**: Bajo la sesión de carga única, login, registro de notas y consulta de boletas mantienen una tasa
  de error inferior al 1 % con el perfil de carga definido en el plan, y los resultados quedan documentados.
- **SC-006**: La boleta de un alumno se genera y descarga en menos de 5 segundos en condiciones normales.

## Assumptions

- Las notas se organizan **por curso, sección y periodo**, con criterios definidos por el Docente; no hay
  catálogo global de criterios ni competencias por área.
- Con escala literal, los equivalentes numéricos para promediar son AD=4, A=3, B=2, C=1 y el resultado se
  redondea a la letra más cercana; el umbral de aprobación por defecto es B en literal y 11 en vigesimal,
  ambos configurables. Esos equivalentes son **configuración de la institución** (una opción razonable por
  defecto para poder promediar), **no una regla del Currículo Nacional**; la institución puede cambiarlos en
  datos y la boleta con escala literal muestra siempre el calificativo literal resultante.
- El cierre de periodo es por sección y lo hace solo el Administrador; no hay flujo de aprobación del Director.
- Hay **un único formato de boleta** (orden de recorte: ya reducida a un formato); sin plantillas editables,
  firmas digitales, envío por correo ni libretas de notas oficiales de ministerio.
- Las notas de conducta, comportamiento, recuperación y subsanación quedan fuera de alcance.
- La boleta de asistencia usa el cálculo del Módulo 4 para el rango del periodo.
- Depende de Autenticación y roles (1), Gestión académica (2), Matrícula (3) y Asistencia (4), ya integrados.
- Los datos de alumnos reales no se usan en staging (Ley 29733).
- Verificación manual: prueba de humo de ~10 min en staging con Administrador y un rol limitado, cierre `v0.6.0`.

### Exclusiones deliberadas

Notas de conducta, recuperaciones, importación/exportación en Excel, plantillas de boleta múltiples o
editables, firma digital, notificaciones a apoderados, y el asistente virtual (Módulo 6).
