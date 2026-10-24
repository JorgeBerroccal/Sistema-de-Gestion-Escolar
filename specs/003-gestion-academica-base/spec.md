# Feature Specification: Gestión Académica Base

**Feature Branch**: `003-gestion-academica-base`

**Created**: 2026-10-24

**Status**: Draft

**Módulo MVP**: 2 — Gestión Académica Base

**Input**: User description: "Módulo 2 del SGE: Gestión Académica Base. Permite configurar la estructura
académica de la institución, de forma genérica, para que la usen los módulos de Matrícula, Asistencia y
Evaluación. Una instalación corresponde a una sola institución (sin multi-tenant). Alcance: datos de la
institución (nombre, código modular, UGEL o dirección regional, niveles que atiende); año escolar con un
solo año vigente y sus periodos de evaluación (bimestres o trimestres) sin superposición y dentro del año;
escala de calificación (literal AD/A/B/C o vigesimal 0–20, solo configuración); grados por nivel y
secciones por grado y año escolar; cursos asociados a un nivel y a uno o más grados; asignación de un
docente a un curso en una sección del año vigente. Roles: el Administrador gestiona todo, el Director
consulta todo, el Docente ve solo sus asignaciones (filtro de pertenencia, fail-closed), Padre de Familia y
Alumno sin acceso. No se elimina lo que ya tenga asignaciones: se desactiva. Un docente desactivado no
recibe asignaciones nuevas. Fuera de alcance: horarios, importación desde Excel, logo, cierre o archivado de
años y todo lo de matrícula, asistencia o notas."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Configurar la institución y el año escolar (Priority: P1)

El Administrador registra los datos de la institución (nombre, código modular, UGEL o dirección regional)
y los niveles que atiende, y define la escala de calificación. Después crea el año escolar, define sus
periodos de evaluación (bimestres o trimestres) con sus fechas, y marca el año como vigente.

**Why this priority**: Todo lo demás cuelga de aquí: las secciones y las asignaciones pertenecen a un año
escolar, y los módulos de Asistencia y Evaluación necesitan saber cuál es el año vigente y sus periodos.

**Independent Test**: Iniciar sesión como Administrador, registrar la institución, crear el año 2026 con
cuatro bimestres y marcarlo como vigente; después crear el año 2027, marcarlo como vigente y comprobar que
el 2026 dejó de serlo.

**Acceptance Scenarios**:

1. **Given** una instalación sin datos de institución, **When** el Administrador registra nombre, código
   modular, UGEL o dirección regional y marca los niveles que atiende, **Then** los datos quedan guardados
   y se muestran en la ficha de la institución.
2. **Given** la ficha de la institución, **When** el Administrador intenta guardarla sin nivel atendido o
   sin nombre, **Then** el sistema la rechaza indicando el campo faltante.
3. **Given** la institución registrada, **When** el Administrador elige la escala literal (AD, A, B, C) o
   la vigesimal (0 a 20), **Then** la elección queda guardada y visible; el sistema no califica nada con
   ella en este módulo.
4. **Given** un año escolar con fechas de inicio y fin, **When** el Administrador define sus periodos con
   fechas dentro del año y sin superposición, **Then** los periodos quedan guardados en orden.
5. **Given** un año escolar, **When** el Administrador define un periodo que se superpone con otro, que
   empieza después de que termina, o que cae total o parcialmente fuera del año, **Then** el sistema lo
   rechaza explicando cuál regla se incumple.
6. **Given** un año vigente y otro año creado, **When** el Administrador marca el segundo como vigente,
   **Then** el segundo pasa a ser el único vigente y el primero deja de serlo, en una sola operación.
7. **Given** dos solicitudes simultáneas para marcar años distintos como vigentes, **When** ambas se
   procesan, **Then** al final existe exactamente un año vigente.

---

### User Story 2 - Definir grados, secciones y cursos (Priority: P1)

El Administrador crea los grados de cada nivel (por ejemplo 1.° a 6.° de primaria y 1.° a 5.° de
secundaria), las secciones de cada grado para un año escolar (A, B, C…, en la cantidad que necesite cada
grado) y los cursos, cada uno asociado a un nivel y a uno o más grados de ese nivel.

**Why this priority**: Sin grados, secciones y cursos no hay nada a lo que asignar docentes ni sobre lo que
matricular, tomar asistencia o evaluar.

**Independent Test**: Crear los grados de primaria, dos secciones (A y B) del 1.° grado en el año vigente y
un curso de Matemática asociado a los seis grados de primaria; comprobar que aparecen en sus listados.

**Acceptance Scenarios**:

1. **Given** un nivel atendido, **When** el Administrador crea sus grados con nombre y orden, **Then** los
   grados quedan ordenados dentro del nivel y no se repite el orden ni el nombre en el mismo nivel.
2. **Given** un grado y un año escolar, **When** el Administrador crea las secciones A, B y C, **Then** las
   tres quedan asociadas a ese grado y año; otro grado puede tener una cantidad distinta de secciones.
3. **Given** una sección «A» ya existente en un grado y año, **When** el Administrador intenta crear otra
   «A» en el mismo grado y año, **Then** el sistema lo rechaza.
4. **Given** un curso nuevo, **When** el Administrador lo asocia a un nivel y a varios grados de ese nivel,
   **Then** el curso queda disponible para las secciones de esos grados.
5. **Given** un curso de un nivel, **When** el Administrador intenta asociarlo a un grado de otro nivel,
   **Then** el sistema lo rechaza.
6. **Given** un grado, sección o curso sin ningún registro dependiente, **When** el Administrador lo
   elimina, **Then** desaparece; si tiene dependientes, el sistema ofrece solo desactivarlo.

---

### User Story 3 - Asignar docentes a cursos y secciones (Priority: P1)

El Administrador vincula a un docente con un curso en una sección del año vigente. En primaria un mismo
docente puede quedar asignado a casi todos los cursos de su sección; en secundaria, cada curso de una
sección puede tener un docente distinto. El modelo es el mismo en ambos casos.

**Why this priority**: Es el resultado práctico del módulo: los módulos de Asistencia y Evaluación
necesitan saber quién enseña qué, en qué sección y en qué año.

**Independent Test**: Asignar a un docente tres cursos de la sección 1.° A de primaria, y a dos docentes
distintos dos cursos de la sección 3.° B de secundaria; comprobar que cada asignación se lista con curso,
sección, año y docente.

**Acceptance Scenarios**:

1. **Given** una sección del año vigente, un curso asociado a su grado y un docente activo, **When** el
   Administrador crea la asignación, **Then** queda registrada con docente, curso, sección y año.
2. **Given** un curso ya asignado en una sección, **When** el Administrador intenta asignarlo a otro
   docente en la misma sección sin quitar la asignación anterior, **Then** el sistema lo rechaza; para
   cambiar de docente el Administrador reasigna.
3. **Given** un docente desactivado, **When** el Administrador intenta asignarle un curso, **Then** el
   sistema lo rechaza; el docente desactivado no aparece como candidato.
4. **Given** una sección de un año que no es el vigente, **When** el Administrador intenta crear una
   asignación en ella, **Then** el sistema lo rechaza.
5. **Given** un curso que no está asociado al grado de la sección, **When** el Administrador intenta
   asignarlo en esa sección, **Then** el sistema lo rechaza.
6. **Given** un usuario cuyo rol no es Docente, **When** el Administrador busca candidatos, **Then** ese
   usuario no aparece y no puede recibir asignaciones.
7. **Given** un docente con varios cursos de una misma sección por asignar, **When** el Administrador los
   selecciona y confirma una vez, **Then** se crean todas las asignaciones; si alguna no es válida no se
   crea ninguna y se informa cuál falló.

---

### User Story 4 - Acceso por rol: consulta del Director y vista propia del Docente (Priority: P2)

El Director consulta toda la estructura académica y todas las asignaciones, sin poder modificar nada. El
Docente ve únicamente sus propias asignaciones (curso, sección y año) y nada más de otros docentes. Padre
de Familia y Alumno no tienen acceso a este módulo.

**Why this priority**: Es el requisito de seguridad del módulo (Principios III y VI de la Constitución),
pero depende de que existan datos que proteger.

**Independent Test**: Con un Administrador, un Director, dos Docentes con asignaciones distintas, un Padre
de Familia y un Alumno, iniciar sesión con cada uno e intentar ver y modificar cada pantalla del módulo.

**Acceptance Scenarios**:

1. **Given** una sesión de Director, **When** consulta la institución, años, periodos, grados, secciones,
   cursos y asignaciones, **Then** ve todo, y los controles de crear, editar, desactivar y eliminar no
   están disponibles; si intenta ejecutarlos directamente, el sistema responde con acceso denegado (403).
2. **Given** una sesión de Docente con asignaciones en dos años escolares, **When** abre «Mis
   asignaciones», **Then** ve únicamente las suyas (curso, sección, año) de ambos años y ninguna de otro
   docente.
3. **Given** un Docente, **When** intenta abrir por dirección directa una asignación de otro docente,
   **Then** el sistema responde con acceso denegado o no encontrado y no revela datos de esa asignación.
4. **Given** un Docente, **When** intenta ver o modificar la institución, los años, los grados, las
   secciones, los cursos o crear asignaciones, **Then** el sistema responde con acceso denegado (403).
5. **Given** un Padre de Familia o un Alumno, **When** intenta abrir cualquier pantalla de este módulo,
   **Then** el sistema responde con acceso denegado (403) y la navegación no se la ofrece.
6. **Given** la regla de pertenencia de las asignaciones, **When** no estuviera registrada, **Then** el
   acceso a las asignaciones propias se deniega (fail-closed) en lugar de mostrar todas.
7. **Given** el Administrador que cambia permisos de un rol desde la gestión del Módulo 1, **When** quita a
   un rol el permiso de consulta, **Then** ese rol deja de acceder sin cambios de código.

---

### User Story 5 - Desactivar en lugar de eliminar (Priority: P2)

Cuando un año, grado, sección o curso ya tiene asignaciones, el Administrador no puede eliminarlo: lo
desactiva. Un elemento desactivado deja de ofrecerse para nuevas asignaciones y selecciones, pero su
historial se conserva y sigue visible; puede reactivarse.

**Why this priority**: Protege el historial sobre el que los módulos de Matrícula, Asistencia y Evaluación
construirán sus datos, pero no bloquea la configuración inicial.

**Independent Test**: Crear una asignación; intentar eliminar su curso, su sección, su grado y su año; ver
que ninguno se elimina y que desactivarlos funciona y mantiene visible la asignación.

**Acceptance Scenarios**:

1. **Given** un curso con al menos una asignación, **When** el Administrador intenta eliminarlo, **Then**
   el sistema lo impide y ofrece desactivarlo.
2. **Given** una sección, un grado o un año con asignaciones (directas o de sus secciones), **When** el
   Administrador intenta eliminarlo, **Then** el sistema lo impide y ofrece desactivarlo.
3. **Given** un curso desactivado, **When** el Administrador crea una asignación, **Then** el curso no se
   ofrece; las asignaciones anteriores del curso siguen visibles y marcadas como de un curso inactivo.
4. **Given** el año vigente, **When** el Administrador intenta desactivarlo, **Then** el sistema lo impide
   hasta que otro año sea marcado como vigente.
5. **Given** un docente que se desactiva desde la gestión de usuarios, **When** el Administrador revisa sus
   asignaciones, **Then** las existentes se conservan y se marcan para reasignar, sin poder crear nuevas.
6. **Given** un elemento desactivado, **When** el Administrador lo reactiva, **Then** vuelve a ofrecerse
   para nuevas asignaciones.

---

### Edge Cases

- Dos años escolares con el mismo nombre o número: se rechaza.
- Periodos con fechas que se tocan en el mismo día (el fin de uno es el inicio de otro): se rechaza; cada
  día pertenece a un solo periodo.
- Cambiar las fechas del año de modo que un periodo existente quede fuera de él: se rechaza hasta ajustar el
  periodo.
- Cambiar el tipo de periodos (bimestre a trimestre) de un año: solo si ningún otro registro depende de los
  periodos; en este módulo nada depende todavía, pero la regla se mantiene para los módulos siguientes.
- Marcar vigente un año desactivado: se rechaza; hay que reactivarlo antes.
- Un docente que deja de tener el rol Docente después de recibir asignaciones: sus asignaciones se
  conservan y se marcan para reasignar, igual que con un docente desactivado.
- Un grado con secciones o cursos asociados pero sin asignaciones: no se elimina (tiene dependientes); se
  desactiva o se eliminan primero sus dependientes.
- Desactivar un nivel que tiene grados: se rechaza mientras tenga grados activos.
- Quitar una asignación del año vigente: el Administrador puede hacerlo; las de años anteriores no se
  modifican.
- Nombres de grados, secciones y cursos con espacios repetidos o distinta capitalización («a» y «A»): se
  normalizan y se tratan como el mismo valor al comprobar duplicados.
- Búsqueda o filtro sin resultados: se muestra un mensaje de lista vacía, no un error.
- Listados con muchos registros: siempre paginados.
- Dos Administradores editan el mismo registro a la vez: gana la última guardada, sin dejar datos
  inconsistentes (por ejemplo, dos años vigentes).

## Requirements *(mandatory)*

### Functional Requirements

**Institución y escala**

- **FR-001**: El sistema MUST mantener una única ficha de institución con nombre, código modular y UGEL o
  dirección regional, editable por el Administrador. No existe más de una ficha.
- **FR-002**: El sistema MUST permitir marcar los niveles que atiende la institución (primaria,
  secundaria o ambos) a partir de un catálogo de niveles guardado en tablas; MUST exigir al menos uno.
- **FR-003**: El sistema MUST guardar la escala de calificación de la institución con dos opciones: literal
  (AD, A, B, C) o vigesimal (0 a 20). Solo se guarda la configuración; este módulo no califica.

**Año escolar y periodos**

- **FR-004**: El sistema MUST permitir crear, editar y desactivar años escolares con nombre, fecha de inicio
  y fecha de fin (inicio anterior al fin).
- **FR-005**: El sistema MUST garantizar que exista como máximo un año vigente, incluso ante operaciones
  simultáneas; marcar un año como vigente MUST dejar de serlo al anterior en la misma operación.
- **FR-006**: Cada año MUST definir su tipo de periodo (bimestres o trimestres) y sus periodos con nombre,
  orden, fecha de inicio y de fin.
- **FR-007**: El sistema MUST rechazar periodos con fechas fuera del año, con inicio posterior al fin, o
  que se superpongan con otro periodo del mismo año.
- **FR-008**: El sistema MUST impedir desactivar el año vigente y marcar como vigente un año desactivado.

**Estructura académica**

- **FR-009**: El sistema MUST permitir crear, editar, ordenar y desactivar grados dentro de un nivel, sin
  repetir nombre ni orden dentro del nivel.
- **FR-010**: El sistema MUST permitir crear, editar y desactivar secciones por grado y año escolar, en
  cantidad variable por grado, sin repetir el identificador de sección dentro del mismo grado y año.
- **FR-011**: El sistema MUST permitir crear, editar y desactivar cursos con un nivel y uno o más grados de
  ese nivel; MUST rechazar asociar un curso a grados de otro nivel.
- **FR-012**: El sistema MUST eliminar físicamente un año, grado, sección o curso solo si no tiene
  registros dependientes (asignaciones ni elementos que dependan de él); en caso contrario MUST ofrecer
  solo desactivarlo.
- **FR-013**: Un elemento desactivado MUST dejar de ofrecerse para nuevas asignaciones y selecciones, MUST
  conservarse con su historial visible y MUST poder reactivarse.

**Asignación docente**

- **FR-014**: El sistema MUST permitir asignar un docente a un curso en una sección de un año escolar,
  registrando docente, curso, sección y año.
- **FR-015**: El sistema MUST aceptar como docente solo a usuarios activos con rol Docente.
- **FR-016**: El sistema MUST aceptar asignaciones solo en secciones del año vigente y solo para cursos
  asociados al grado de la sección.
- **FR-017**: El sistema MUST permitir un único docente por curso en cada sección, MUST permitir que un
  mismo docente tenga varios cursos de una o varias secciones, y MUST permitir reasignar o quitar una
  asignación del año vigente.
- **FR-018**: El sistema MUST permitir asignar a un docente varios cursos de una misma sección en una sola
  operación, que se aplica completa o no se aplica (todo o nada).
- **FR-019**: Las asignaciones existentes de un docente desactivado o que dejó de tener rol Docente MUST
  conservarse y marcarse para reasignar, y MUST NOT crearse asignaciones nuevas para él.

**Roles, permisos y pertenencia**

- **FR-020**: El sistema MUST autorizar toda acción del módulo contra los permisos del rol en las tablas de
  RBAC del Módulo 1, nunca por el nombre del rol.
- **FR-021**: Los permisos nuevos del módulo MUST añadirse a la carga inicial de roles y permisos, con la
  asignación por defecto: Administrador gestiona todo; Director solo consulta todo; Docente solo consulta
  sus asignaciones; Padre de Familia y Alumno sin permisos del módulo.
- **FR-022**: La consulta de asignaciones MUST usar el mecanismo de filtros de pertenencia del Módulo 1: el
  Docente recibe solo las asignaciones en que figura como docente (de cualquier año); si el tipo de
  registro requiere pertenencia y no tiene regla registrada, el acceso se deniega (fail-closed).
- **FR-023**: Quienes tienen permiso de consulta total (Administrador y Director) MUST ver todas las
  asignaciones; el Director MUST NOT ver ni poder ejecutar acciones de modificación.
- **FR-024**: Los nombres de instituciones, niveles, grados, secciones, cursos y escalas MUST residir solo
  en datos; los datos de ejemplo solo pueden existir en cargas iniciales de ejemplo, no en el código.

**Rendimiento y calidad**

- **FR-025**: Todo listado del módulo MUST estar paginado, cargar sus relaciones de forma anticipada y
  apoyarse en índices para toda clave foránea y columna de búsqueda, filtro u orden.
- **FR-026**: Cada regla de autorización y cada regla de validación (periodos, año vigente único,
  duplicados, desactivación en lugar de eliminación, docente elegible) MUST tener pruebas de acceso
  permitido y denegado o de caso válido e inválido.
- **FR-027**: Los permisos nuevos MUST llegar al servidor de staging en el despliegue mediante la
  sincronización de roles y permisos del procedimiento de despliegue, sin pasos manuales. La tarea T086 que
  se citaba de la especificación 002 no existe (su lista termina en T085): esta sincronización se entrega
  como tarea de esta funcionalidad.

### Key Entities

- **Institución**: la única ficha de la instalación: nombre, código modular, UGEL o dirección regional y
  escala de calificación elegida.
- **Nivel**: catálogo de niveles (por ejemplo primaria y secundaria como datos de ejemplo); la institución
  marca los que atiende.
- **Año escolar**: nombre, fechas de inicio y fin, tipo de periodo, indicador de vigente (uno solo) y
  estado activo.
- **Periodo de evaluación**: pertenece a un año; nombre, orden, fechas de inicio y fin sin superposición.
- **Grado**: pertenece a un nivel; nombre, orden y estado activo.
- **Sección**: pertenece a un grado y a un año escolar; identificador (A, B, C…) y estado activo.
- **Curso**: nombre, nivel y conjunto de grados de ese nivel en que se dicta; estado activo.
- **Asignación docente**: vincula un usuario docente con un curso en una sección (que ya implica el grado y
  el año); único por curso y sección.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Un Administrador puede dejar configurada una institución nueva (ficha, escala, año con sus
  periodos, grados, 3 secciones por grado, cursos y asignaciones de una sección) en menos de 30 minutos
  siguiendo solo la interfaz.
- **SC-002**: Ninguna combinación de operaciones deja a la instalación con más de un año vigente, periodos
  superpuestos o fuera del año, o asignaciones de un curso a dos docentes en la misma sección; lo verifican
  pruebas automáticas incluida la operación simultánea.
- **SC-003**: El 100 % de los intentos de un Docente, un Padre de Familia o un Alumno de ver datos que su
  rol no permite (asignaciones de otro docente, configuración, acciones de modificación) termina en
  acceso denegado sin revelar datos, comprobado por pruebas con cada rol.
- **SC-004**: El 100 % de los intentos de eliminar un año, grado, sección o curso con asignaciones se
  impide, y el historial de esas asignaciones sigue consultable tras desactivar el elemento.
- **SC-005**: Con 200 secciones y 2000 asignaciones cargadas, cada listado del módulo muestra su primera
  página en menos de 2 segundos, y el listado «Mis asignaciones» de un Docente en menos de 1,5 segundos.
- **SC-006**: Tras un despliegue en staging, los usuarios de ejemplo de cada rol ven exactamente los
  permisos esperados del módulo sin ejecutar pasos manuales.
- **SC-007**: Una institución cualquiera, con otros niveles y cantidad de grados y secciones, queda
  configurada solo con datos, sin cambiar el código; lo demuestra una carga de ejemplo distinta a la
  habitual.

## Assumptions

- **Módulo y dependencias**: esta especificación pertenece al Módulo 2 del MVP. Depende del Módulo 1
  (usuarios, roles, permisos y filtros de pertenencia) y de la sincronización de permisos del despliegue
  (que entrega esta misma funcionalidad, ver FR-027); su primer despliegue previsto es `v0.3.0`.
- **Niveles como datos**: los niveles son registros de un catálogo (Constitución, Principio II); primaria y
  secundaria solo existen como datos de ejemplo. La institución elige cuáles atiende.
- **Escala**: se ofrecen exactamente las dos escalas pedidas, con los valores propios de cada una (AD, A, B,
  C; 0 a 20); no se pueden definir escalas personalizadas. Cambiarla cuando ya existan notas será una regla
  del Módulo 5; aquí la configuración es editable.
- **Año vigente inicial**: crear un año no lo marca automáticamente como vigente; el Administrador lo
  decide. Sin año vigente no se pueden crear asignaciones.
- **Cantidad de periodos**: 4 si el año usa bimestres y 3 si usa trimestres; los nombres se proponen
  automáticamente («I Bimestre»…) y las fechas se completan por el Administrador. Los periodos pueden dejar
  días libres entre ellos (vacaciones).
- **Sin límite de años solapados**: no se exige que los años escolares no se solapen entre sí; solo se
  valida el orden de sus fechas y sus periodos.
- **Grados globales, secciones por año**: los grados pertenecen al nivel y se reutilizan cada año; las
  secciones se crean para cada año escolar. No se incluye copiar la estructura del año anterior.
- **Capacidad**: las secciones no tienen aforo en este módulo; el límite de alumnos, si se necesita, lo
  define el Módulo de Matrícula.
- **Quién es docente**: se define por permiso, igual que la cuenta administradora del Módulo 1: un docente es
  un usuario activo cuyo rol tiene el permiso `docencia.dictar`. El código solo nombra el permiso, nunca el
  rol; el seeder lo asigna por defecto al rol Docente y no se añade ninguna columna a la tabla de roles.
- **Permisos base del módulo** (identificadores ilustrativos; el plan definirá los finales):

  | Permiso | Administrador | Director | Docente | Padre de Familia | Alumno |
  |---------|:-------------:|:--------:|:-------:|:----------------:|:------:|
  | Ver institución, años, periodos, grados, secciones y cursos | ✔ | ✔ | | | |
  | Gestionar institución, años, periodos, grados, secciones y cursos | ✔ | | | | |
  | Ver todas las asignaciones | ✔ | ✔ | | | |
  | Ver las asignaciones propias | | | ✔ | | |
  | Gestionar asignaciones | ✔ | | | | |
  | Dictar clases (`docencia.dictar`, define quién puede recibir asignaciones) | | | ✔ | | |

- **Historial del docente**: el Docente ve sus asignaciones de todos los años, no solo del vigente; no ve
  datos de otros docentes ni de la configuración.
- **Quitar asignaciones**: el Administrador puede quitar asignaciones del año vigente porque aún no hay
  registros de asistencia ni de notas que dependan de ellas; cuando los módulos siguientes agreguen
  dependientes, la regla de FR-012 las protegerá. En particular, en los Módulos 4 (Asistencia) y 5
  (Evaluación) no se podrá quitar una asignación que tenga asistencia o notas registradas.
- **Alumnos y matrícula**: este módulo no maneja alumnos; la relación de estudiantes con secciones es del
  Módulo 3 (Matrícula).
- **Datos de ejemplo**: se entrega una carga de ejemplo (institución ficticia, niveles, grados, secciones,
  cursos y algunas asignaciones) solo para desarrollo y staging; no se carga en producción.
- **Fuera de alcance**: horarios de clase, importación masiva desde Excel, logo de la institución, cierre o
  archivado de años escolares y todo lo de matrícula, asistencia y notas.
