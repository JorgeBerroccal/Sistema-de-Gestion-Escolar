# Feature Specification: Matrícula

**Feature Branch**: `004-matricula`

**Created**: 2026-11-05

**Status**: Draft

**Módulo MVP**: 3 — Matrícula

**Input**: User description: "Módulo 3 del SGE: Matrícula. Permite registrar a los alumnos de la institución y matricularlos en una sección de un año escolar, y vincular a cada familia con sus hijos, de forma genérica, sobre la base académica del Módulo 2. Alcance: ficha del alumno (nombres, apellidos, documento de identidad con tipo y número, fecha de nacimiento, sexo; la unicidad es por tipo y número y el DNI exige 8 dígitos; se desactiva, no se elimina, si tiene matrículas; puede tener un usuario con rol Alumno); apoderados (uno o más usuarios Padre de Familia por alumno, con parentesco; un apoderado puede tener varios hijos); matrícula individual y masiva en una sección con estados matriculado, retirado y trasladado, y un máximo de una matrícula activa por alumno y año; nómina paginada con búsqueda por nombre o documento y ficha con historial; Administrador gestiona, Director consulta, Docente ve la nómina de sus secciones, Padre de Familia y Alumno solo ven lo propio. Los datos de alumnos y apoderados de los seeders de ejemplo y de staging son siempre ficticios (Ley 29733). Fuera de alcance, de forma deliberada: aforo por sección y promoción automática al año siguiente; también asistencia, notas y pagos."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Registrar alumnos y matricularlos en una sección (Priority: P1)

El Administrador registra la ficha de un alumno (nombres, apellidos, tipo y número de documento, fecha de
nacimiento y sexo) y lo matricula en una sección del año vigente. Para varios alumnos a la vez, elige la
sección y marca a los alumnos que quiere matricular en una sola operación.

**Why this priority**: Sin alumnos matriculados no existe nada que asistir ni evaluar; es la base de los
Módulos 4 y 5.

**Independent Test**: Iniciar sesión como Administrador, registrar tres alumnos, matricular uno en una
sección y los otros dos de forma masiva en otra; comprobar que cada sección muestra su nómina.

**Acceptance Scenarios**:

1. **Given** un Administrador, **When** registra un alumno con nombres, apellidos, tipo y número de documento,
   fecha de nacimiento y sexo válidos, **Then** la ficha queda guardada y activa.
2. **Given** un documento de tipo DNI, **When** el número no tiene exactamente 8 dígitos numéricos, **Then**
   el sistema rechaza la ficha indicando la regla incumplida.
3. **Given** un alumno ya registrado con tipo y número de documento, **When** se intenta registrar otro con
   el mismo tipo y número, **Then** el sistema lo rechaza; el mismo número con otro tipo de documento sí se
   acepta.
4. **Given** una ficha con fecha de nacimiento futura o con nombres o apellidos vacíos, **When** se guarda,
   **Then** el sistema la rechaza indicando el campo.
5. **Given** un alumno activo sin matrícula activa en el año vigente y una sección activa de ese año,
   **When** el Administrador lo matricula, **Then** la matrícula queda en estado «matriculado» con su fecha.
6. **Given** un alumno con matrícula activa en el año vigente, **When** se intenta matricularlo en otra
   sección del mismo año, **Then** el sistema lo rechaza explicando que ya está matriculado.
7. **Given** una sección y varios alumnos elegibles, **When** el Administrador los matricula de forma masiva
   y uno de ellos ya no es elegible (por ejemplo, ya tiene matrícula activa), **Then** la operación no se
   aplica a ninguno y el sistema indica cuál alumno la impide (todo o nada).
8. **Given** un alumno desactivado o una sección desactivada o de un año que no es el vigente, **When** se
   intenta matricular, **Then** el sistema lo rechaza y esas opciones no se ofrecen en el formulario.

---

### User Story 2 - Retirar, trasladar y consultar el historial (Priority: P1)

El Administrador cambia el estado de una matrícula a «retirado» o «trasladado» con su fecha y un motivo
opcional, y puede corregirlo. Cada alumno conserva su historial de matrículas de todos los años. Un alumno
retirado o trasladado puede volver a matricularse en el mismo año.

**Why this priority**: La nómina real cambia durante el año; sin estados la nómina deja de reflejar quién
asiste, y Asistencia y Evaluación necesitan distinguir a los alumnos vigentes de los que ya no están.

**Independent Test**: Matricular un alumno, retirarlo con motivo, ver que sale de la nómina activa pero
sigue en su historial; volver a matricularlo en otra sección del mismo año.

**Acceptance Scenarios**:

1. **Given** una matrícula en estado «matriculado», **When** el Administrador la marca como «retirado» o
   «trasladado» con fecha y, si quiere, un motivo, **Then** el estado, la fecha y el motivo quedan
   registrados y el alumno deja de figurar en la nómina activa de la sección.
2. **Given** una matrícula retirada o trasladada, **When** el Administrador la corrige a «matriculado»
   porque se registró por error, **Then** vuelve a la nómina activa, siempre que el alumno no tenga otra
   matrícula activa en ese año.
3. **Given** un alumno con una matrícula retirada en el año vigente, **When** se le matricula de nuevo en
   una sección del mismo año, **Then** se crea una matrícula nueva y la anterior se conserva en su historial.
4. **Given** una matrícula con fecha de cambio de estado anterior a la fecha de la matrícula, **When** se
   guarda, **Then** el sistema la rechaza.
5. **Given** un alumno con matrículas de varios años, **When** se abre su ficha, **Then** se listan todas
   sus matrículas con año, grado, sección, estado, fechas y motivo.
6. **Given** un alumno con matrícula activa, **When** el Administrador intenta desactivarlo, **Then** el
   sistema lo impide pidiendo retirar o trasladar antes la matrícula; **y** un alumno con cualquier
   matrícula nunca se elimina, solo se desactiva.
7. **Given** un alumno sin ninguna matrícula, **When** el Administrador lo elimina, **Then** la ficha se
   borra físicamente.

---

### User Story 3 - Vincular alumnos con sus usuarios y sus apoderados (Priority: P2)

El Administrador vincula la ficha de un alumno con su usuario (para que el alumno pueda iniciar sesión y ver
sus datos) y vincula al alumno con uno o más usuarios de Padre de Familia, indicando el parentesco. Un
apoderado puede tener varios hijos.

**Why this priority**: Permite que las familias vean la información de sus hijos en los módulos siguientes,
pero la nómina funciona sin ello.

**Independent Test**: Vincular un usuario Alumno a su ficha y dos Padres de Familia a la misma ficha; vincular
uno de esos padres a un segundo hijo; comprobar que cada vínculo se muestra en la ficha del alumno.

**Acceptance Scenarios**:

1. **Given** un usuario activo con permiso de ser alumno que no está vinculado a otra ficha, **When** el
   Administrador lo vincula a la ficha de un alumno, **Then** el vínculo queda guardado y ese usuario ve esa
   ficha como propia.
2. **Given** un usuario ya vinculado a la ficha de un alumno, **When** se intenta vincular a otra ficha,
   **Then** el sistema lo rechaza; y a un usuario sin permiso de ser alumno tampoco se le puede vincular.
3. **Given** un usuario activo con permiso de ser apoderado, **When** el Administrador lo vincula a un
   alumno con un parentesco (por ejemplo madre, padre, tutor u otro), **Then** el vínculo queda registrado.
4. **Given** un apoderado vinculado a dos alumnos, **When** se consulta cada ficha, **Then** el apoderado
   figura en ambas.
5. **Given** un alumno con varios apoderados, **When** se quita el vínculo de uno, **Then** los demás se
   conservan; el mismo usuario no puede figurar dos veces como apoderado del mismo alumno.
6. **Given** un usuario que no tiene permiso de ser apoderado (por ejemplo un Docente), **When** se intenta
   vincular como apoderado, **Then** el sistema lo rechaza.

---

### User Story 4 - Consultar nóminas y datos según el rol (Priority: P2)

Cada rol ve únicamente lo que le corresponde: el Administrador y el Director consultan alumnos, nóminas e
historiales de todos; el Docente consulta la nómina de las secciones donde tiene asignaciones; el Padre de
Familia ve las fichas y matrículas de sus hijos; el Alumno ve su propia ficha y matrículas. La nómina es
paginada y se puede buscar por nombre o documento.

**Why this priority**: Es la protección de datos personales de menores; sin ella los demás módulos no
pueden mostrar información de alumnos con seguridad.

**Independent Test**: Iniciar sesión con cada rol e intentar ver, buscar y modificar alumnos y nóminas
ajenas y propias; comprobar que solo se ve lo permitido y que las modificaciones se rechazan.

**Acceptance Scenarios**:

1. **Given** un Administrador o un Director, **When** abre el listado de alumnos y la nómina de una sección,
   **Then** ve todos, paginados, y puede buscar por nombre, apellido o número de documento.
2. **Given** un Director, **When** intenta crear, editar, desactivar, matricular, retirar o vincular,
   **Then** el sistema lo deniega y no ofrece esas acciones.
3. **Given** un Docente con asignación en una sección, **When** abre las nóminas, **Then** ve solo las
   secciones donde tiene asignaciones (de cualquier año) y, de cada alumno, nombres, apellidos y
   documento; no ve apoderados ni otras secciones.
4. **Given** un Docente, **When** intenta abrir la nómina de una sección donde no tiene asignaciones o la
   ficha completa de un alumno, **Then** el acceso se deniega sin revelar datos.
5. **Given** un Padre de Familia, **When** abre su área, **Then** ve solo a los alumnos de los que es
   apoderado, con su ficha y su historial de matrículas; si intenta abrir a otro alumno, se deniega.
6. **Given** un Alumno vinculado a su ficha, **When** abre su área, **Then** ve solo su propia ficha y
   matrículas; no ve compañeros ni nóminas.
7. **Given** un usuario sin ningún vínculo a un alumno, **When** abre el área propia, **Then** ve una lista
   vacía, nunca datos de otros (acceso denegado por defecto).
8. **Given** el Administrador que quita al rol Director el permiso de consulta de alumnos desde la gestión
   de roles, **When** el Director vuelve a entrar, **Then** pierde el acceso sin cambiar código.

---

### Edge Cases

- Dos solicitudes simultáneas para matricular al mismo alumno en el mismo año: solo una se aplica; el
  alumno nunca queda con dos matrículas activas en el mismo año.
- Una sección que se desactiva con alumnos matriculados: sus matrículas y su historial se conservan
  visibles; no se ofrece para matrículas nuevas.
- Un usuario vinculado como alumno o apoderado que se desactiva o pierde el permiso correspondiente: el
  vínculo se conserva pero deja de dar acceso a datos.
- Búsqueda sin coincidencias: lista vacía con mensaje, nunca error.
- Nombres con tildes, eñes o varios apellidos: la búsqueda no distingue mayúsculas ni tildes.
- Un número de documento con espacios al inicio o al final se normaliza antes de validar y comparar.
- Año vigente inexistente: no se puede matricular; el sistema lo indica.

## Requirements *(mandatory)*

### Functional Requirements

**Alumnos**

- **FR-001**: El sistema MUST permitir crear, editar y desactivar la ficha de un alumno con nombres,
  apellidos, tipo de documento, número de documento, fecha de nacimiento y sexo; todos obligatorios.
- **FR-002**: El tipo de documento MUST ser uno de un catálogo con DNI, CE y «otro». El sistema MUST rechazar
  un DNI que no tenga exactamente 8 dígitos numéricos, y aplicar formato razonable a los otros tipos.
- **FR-003**: El sistema MUST garantizar que la pareja (tipo de documento, número) sea única entre los
  alumnos, incluso ante operaciones simultáneas, y MUST normalizar espacios del número antes de validar.
- **FR-004**: El sistema MUST rechazar fechas de nacimiento futuras.
- **FR-005**: El sistema MUST eliminar físicamente a un alumno solo si no tiene matrículas; en caso contrario
  MUST ofrecer solo desactivarlo, conservando su historial. Un alumno con matrícula activa MUST NOT poder
  desactivarse hasta retirar o trasladar la matrícula.
- **FR-006**: Un alumno desactivado MUST dejar de ofrecerse para matrículas nuevas, conservar su historial
  visible y poder reactivarse.

**Vínculos con usuarios y apoderados**

- **FR-007**: El sistema MUST permitir vincular una ficha de alumno con a lo más un usuario, y un usuario con
  a lo más una ficha. Solo se aceptan usuarios activos con el permiso que los habilita como alumno.
- **FR-008**: El sistema MUST permitir vincular a un alumno con uno o más apoderados, cada uno con su
  parentesco (madre, padre, tutor u otro), y a un apoderado con varios alumnos; el mismo apoderado no puede
  repetirse en el mismo alumno. Solo se aceptan usuarios activos con el permiso que los habilita como
  apoderado.
- **FR-009**: Quién es alumno y quién es apoderado MUST definirse por permiso (como «quién es docente» en el
  Módulo 2), nunca por el nombre del rol en el código.
- **FR-010**: El sistema MUST permitir quitar un vínculo de usuario o de apoderado sin afectar el resto de la
  ficha ni las matrículas.

**Matrícula**

- **FR-011**: El sistema MUST permitir matricular a un alumno activo en una sección activa del año vigente,
  registrando alumno, sección (que implica grado y año), fecha de matrícula y estado «matriculado».
- **FR-012**: El sistema MUST garantizar como máximo una matrícula activa («matriculado») por alumno y año
  escolar, incluso ante operaciones simultáneas.
- **FR-013**: El sistema MUST permitir matricular varios alumnos en una misma sección en una sola operación,
  que se aplica completa o no se aplica (todo o nada), indicando cuál alumno la impide.
- **FR-014**: El sistema MUST permitir cambiar el estado de una matrícula a «retirado» o «trasladado» con
  fecha (no anterior a la de matrícula) y motivo opcional, y corregirla de vuelta a «matriculado» si no hay
  otra matrícula activa del alumno en ese año.
- **FR-015**: El sistema MUST permitir matricular de nuevo, en el mismo año, a un alumno cuya matrícula esté
  retirada o trasladada, conservando las anteriores como historial.
- **FR-016**: El sistema MUST conservar todas las matrículas de un alumno y mostrarlas en su historial con
  año, grado, sección, estado, fechas y motivo; una matrícula MUST NOT eliminarse una vez creada (se
  cambia su estado).
- **FR-017**: Una sección con matrículas MUST seguir sin poder eliminarse (se desactiva), ampliando la
  regla de eliminación de elementos con dependientes del Módulo 2.

**Consulta**

- **FR-018**: El sistema MUST ofrecer un listado de alumnos y una nómina por sección y año, ambos paginados,
  con búsqueda por nombre, apellido o número de documento (sin distinguir mayúsculas ni tildes) y filtro por
  estado de la matrícula.
- **FR-019**: El sistema MUST ofrecer la ficha del alumno con sus datos, apoderados y historial de
  matrículas, a quien tenga permiso para verla.

**Roles, permisos y pertenencia**

- **FR-020**: El sistema MUST autorizar toda acción del módulo contra los permisos del rol en las tablas de
  RBAC del Módulo 1, nunca por el nombre del rol.
- **FR-021**: Los permisos nuevos MUST añadirse a la carga inicial de roles y permisos con la asignación por
  defecto: Administrador gestiona todo; Director solo consulta todo; Docente solo consulta la nómina de sus
  secciones; Padre de Familia y Alumno solo consultan lo propio; el permiso de ser alumno se asigna al rol
  Alumno y el de ser apoderado al rol Padre de Familia.
- **FR-022**: La consulta del Docente MUST usar el mecanismo de filtros de pertenencia del Módulo 1: solo ve
  las secciones donde figura en una asignación docente y, de cada alumno, nombres, apellidos y documento.
- **FR-023**: La consulta del Padre de Familia y del Alumno MUST usar el mismo mecanismo: el apoderado solo
  recibe los alumnos con los que está vinculado y el Alumno solo su ficha; si no hay regla de pertenencia o
  vínculo, el acceso se deniega (fail-closed).
- **FR-024**: El Director MUST NOT ver ni poder ejecutar acciones de modificación.

**Datos personales y datos de ejemplo**

- **FR-025**: Los datos de alumnos y apoderados de las cargas de ejemplo (seeders) y de staging MUST ser
  siempre ficticios, incluidos nombres, documentos, fechas de nacimiento y correos, porque incluyen datos
  personales de menores (Ley 29733, Protección de Datos Personales). Las cargas de ejemplo MUST rechazar
  ejecutarse en producción, y ningún dato real de menores se carga fuera de producción.
- **FR-026**: Los datos de alumnos MUST mostrarse solo a quien tenga permiso para verlos, y MUST NOT
  aparecer en mensajes de error, registros técnicos ni en datos de búsqueda de quien no tiene acceso.
- **FR-027**: Los nombres de instituciones, niveles, grados, secciones y documentos de ejemplo MUST residir
  solo en datos, no en el código.

**Rendimiento y calidad**

- **FR-028**: Todo listado del módulo MUST estar paginado, cargar sus relaciones de forma anticipada y
  apoyarse en índices para toda clave foránea y columna de búsqueda, filtro u orden.
- **FR-029**: Cada regla de autorización y de validación (documento único por tipo y número, DNI de 8
  dígitos, una matrícula activa por año, todo o nada en la masiva, eliminación frente a desactivación,
  pertenencia por rol) MUST tener pruebas de acceso permitido y denegado o de caso válido e inválido.
- **FR-030**: Los permisos nuevos MUST llegar al servidor de staging en el despliegue mediante la
  sincronización de datos base existente, sin pasos manuales.

### Key Entities

- **Alumno**: nombres, apellidos, tipo y número de documento (único por pareja), fecha de nacimiento, sexo,
  estado activo y, opcionalmente, el usuario vinculado.
- **Tipo de documento**: catálogo (DNI, CE, otro) con su regla de formato.
- **Vínculo de apoderado**: une un alumno con un usuario apoderado e indica el parentesco; único por alumno
  y apoderado.
- **Matrícula**: une un alumno con una sección (que implica grado y año escolar); fecha, estado
  (matriculado, retirado o trasladado), fecha del cambio de estado y motivo opcional. A lo más una
  «matriculado» por alumno y año.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Un Administrador puede registrar alumnos y matricularlos, de forma individual y masiva, y
  consultar la nómina de la sección, usando solo la interfaz; lo demuestran el recorrido automatizado del
  quickstart y una prueba de humo manual en staging.
- **SC-002**: Ninguna combinación de operaciones, incluidas las simultáneas, deja a un alumno con más de una
  matrícula activa en un año, con un documento repetido (mismo tipo y número) o con un DNI que no tenga 8
  dígitos; lo verifican pruebas automáticas.
- **SC-003**: El 100 % de los intentos de un Docente, un Padre de Familia o un Alumno de ver alumnos,
  nóminas o apoderados que su pertenencia no permite, o de modificar algo, termina en acceso denegado sin
  revelar datos, comprobado con pruebas por cada rol.
- **SC-004**: El 100 % de los intentos de eliminar un alumno con matrículas o una sección con matrículas se
  impide, y el historial sigue consultable tras desactivarlos.
- **SC-005**: Con 2000 alumnos y 200 secciones cargados, el listado de alumnos y la nómina de una sección
  muestran su primera página, y la búsqueda devuelve resultados, en menos de 2 segundos, con un número de
  consultas que no crece con el tamaño de la página (sin N+1).
- **SC-006**: Tras un despliegue en staging, los usuarios de ejemplo de cada rol ven exactamente lo que su
  rol permite en este módulo sin pasos manuales, y todos los datos de alumnos y apoderados de staging son
  ficticios.
- **SC-007**: El código y las vistas del módulo no contienen nombres de nivel, grado, sección ni institución
  de ejemplo; lo verifica una prueba automática como en el Módulo 2.

## Assumptions

- **Módulo y dependencias**: esta especificación pertenece al Módulo 3 del MVP. Depende del Módulo 1
  (usuarios, roles, permisos y filtros de pertenencia) y del Módulo 2 (año vigente, secciones y asignaciones
  docentes); su despliegue previsto es `v0.4.0`.
- **Solo el año vigente**: se matricula únicamente en secciones del año vigente, igual que las asignaciones;
  el historial de años anteriores se consulta pero no se modifica.
- **Matrícula cada año desde cero**: no hay promoción ni traslado automático al año siguiente; cada año se
  matricula de nuevo (ver Exclusiones deliberadas).
- **Cambio de sección**: dentro del año se hace retirando o trasladando la matrícula y matriculando de
  nuevo (FR-014 y FR-015); no hay una operación de «mover» aparte.
- **Formato de documentos**: DNI exige 8 dígitos; CE acepta de 9 a 12 caracteres alfanuméricos; «otro»
  acepta de 4 a 20 caracteres alfanuméricos. El catálogo de tipos es de datos base (no de ejemplo).
- **Sexo**: valores «femenino» y «masculino».
- **Quién es alumno o apoderado**: se define por permiso (ser alumno, ser apoderado), asignado por defecto a
  los roles Alumno y Padre de Familia; el código solo nombra el permiso, nunca el rol. Los identificadores
  finales los define el plan.
- **Permisos base del módulo** (ilustrativos; el plan fija los finales):

  | Permiso | Administrador | Director | Docente | Padre de Familia | Alumno |
  |---------|:-------------:|:--------:|:-------:|:----------------:|:------:|
  | Ver todos los alumnos, nóminas y matrículas | ✔ | ✔ | | | |
  | Gestionar alumnos, vínculos y matrículas | ✔ | | | | |
  | Ver nóminas de las secciones propias | | | ✔ | | |
  | Ver los alumnos y matrículas propios o de los hijos | | | | ✔ | ✔ |
  | Ser alumno (puede vincularse a una ficha) | | | | | ✔ |
  | Ser apoderado (puede vincularse a alumnos) | | | | ✔ | |

- **Datos que ve el Docente**: nombres, apellidos y documento de los alumnos de sus secciones; no ve fecha de
  nacimiento, apoderados ni datos de contacto.
- **Datos de ejemplo**: se entrega una carga de ejemplo con alumnos y apoderados ficticios para desarrollo y
  staging; no se carga en producción. Nada de lo cargado en staging proviene de personas reales.
- **Sin importación ni exportación**: no se importan alumnos desde Excel ni se exporta la nómina.
- **Verificación**: la prueba manual en staging es una prueba de humo de unos 10 minutos (flujo principal con
  el Administrador y un rol limitado); el resto queda cubierto por pruebas automáticas (Constitución 2.2.0).
  No hay endpoints críticos de carga en este módulo.

### Exclusiones deliberadas

- **Aforo o límite de alumnos por sección**: el Módulo 2 lo había dejado a este módulo; se descarta
  deliberadamente. Las secciones no tienen capacidad máxima.
- **Promoción o traslado automático al año siguiente**: se descarta deliberadamente; la matrícula se hace
  cada año desde cero.
- **También fuera de alcance**: asistencia, notas, pagos, datos de contacto y domicilio, fotografías,
  importación desde Excel, constancias de matrícula e impresión de nóminas.
