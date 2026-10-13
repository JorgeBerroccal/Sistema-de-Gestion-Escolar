# Feature Specification: Autenticación y Roles (RBAC)

**Feature Branch**: `001-auth-roles-rbac`

**Created**: 2026-09-27

**Status**: Draft

**Módulo MVP**: 1 — Autenticación y Roles

**Input**: User description: "Módulo de Autenticación y Roles del SGE. Un usuario inicia sesión con
correo/usuario y contraseña. Cada usuario tiene exactamente un rol asignado de los 5 definidos:
Administrador, Director, Docente, Padre de Familia, Alumno. El sistema implementa RBAC mediante
tablas: Rol, Permiso y Rol_Permiso (relación muchos a muchos). Ningún permiso se codifica de forma
fija en el código; todo se resuelve consultando Rol_Permiso en tiempo de ejecución. El Administrador
puede: crear, editar y desactivar cuentas de usuario; asignar o cambiar el rol de un usuario; crear
y editar permisos, y modificar qué permisos tiene cada rol. Este módulo también debe dejar preparado
(pero no implementado todavía) el mecanismo de 'filtros de pertenencia' [...] Criterios de
aceptación: credenciales inválidas no inician sesión; menú según permisos del rol; acción sin
permiso rechazada (403) con test de acceso denegado; cuenta desactivada no puede iniciar sesión;
seeder con los 5 roles iniciales y sus permisos base."

## Clarifications

### Session 2026-10-12

- Q: ¿Qué carga debe soportar el módulo? → A: La prueba de carga (SC-006) debe llegar a 1000
  usuarios virtuales; la constitución v2.1.0 incorpora Redis y las reglas de rendimiento
  (índices, `with()`, paginación) para lograrlo en un VPS de 2 vCPU / 4 GB.

### Session 2026-09-27

- Q: ¿Qué debe ocurrir cuando se consulta un tipo de registro que requiere pertenencia (p. ej.
  datos de un estudiante) y no tiene una regla de pertenencia registrada? → A: Fail-closed: el
  acceso se deniega por defecto (403); nunca se devuelven los datos sin filtrar.
- Q: ¿Se crea obligatoriamente una cuenta con rol Alumno para cada estudiante matriculado? → A: No.
  Es opcional y la decide el Administrador al gestionar usuarios; en primaria normalmente accede el
  Padre de Familia en nombre del estudiante y en secundaria es más común habilitar la cuenta del
  propio estudiante. Los 5 roles no cambian.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Iniciar y cerrar sesión (Priority: P1)

Cualquier usuario registrado (Administrador, Director, Docente, Padre de Familia o Alumno) ingresa
al sistema escribiendo su correo electrónico o su nombre de usuario junto con su contraseña. Si las
credenciales son correctas y la cuenta está activa, accede a su página de inicio. Si no, se le
muestra un mensaje de error genérico y permanece en la pantalla de inicio de sesión. Puede cerrar
sesión en cualquier momento.

**Why this priority**: Sin autenticación ningún otro módulo del SGE puede funcionar; es la puerta de
entrada de todo el sistema.

**Independent Test**: Con los usuarios de ejemplo cargados por el seeder, se puede probar iniciar
sesión con credenciales válidas e inválidas, y cerrar sesión, sin ninguna otra funcionalidad.

**Acceptance Scenarios**:

1. **Given** un usuario activo con correo y contraseña válidos, **When** ingresa su correo y
   contraseña, **Then** accede a su página de inicio.
2. **Given** un usuario activo, **When** ingresa su nombre de usuario (en lugar del correo) y su
   contraseña, **Then** accede a su página de inicio.
3. **Given** un usuario existente, **When** ingresa una contraseña incorrecta, **Then** no inicia
   sesión y ve un mensaje genérico que no revela si el usuario existe.
4. **Given** un identificador que no corresponde a ningún usuario, **When** intenta iniciar sesión,
   **Then** no inicia sesión y ve el mismo mensaje genérico.
5. **Given** un usuario autenticado, **When** cierra sesión, **Then** su sesión termina y cualquier
   intento posterior de acceder a una página protegida lo redirige al inicio de sesión.
6. **Given** un visitante no autenticado, **When** intenta acceder a una página protegida,
   **Then** es redirigido a la pantalla de inicio de sesión.

---

### User Story 2 - Autorización por permisos del rol (Priority: P1)

Una vez autenticado, cada usuario solo ve en el menú las opciones para las que su rol tiene permiso,
y el sistema rechaza cualquier intento de ejecutar una acción para la que su rol no tiene el permiso
correspondiente, aunque el usuario conozca la dirección directa de esa acción. Los permisos se
determinan exclusivamente por la asignación rol–permiso almacenada, no por el nombre del rol.

**Why this priority**: Es el núcleo de seguridad exigido por la constitución (Principio III); todos
los módulos posteriores dependen de este mecanismo.

**Independent Test**: Con los roles y permisos del seeder, iniciar sesión con un usuario de cada rol
y verificar qué opciones de menú aparecen y que las acciones no permitidas devuelven "acceso
denegado" (403).

**Acceptance Scenarios**:

1. **Given** un usuario cuyo rol tiene el permiso "ver usuarios", **When** abre el menú, **Then**
   ve la opción de gestión de usuarios.
2. **Given** un usuario cuyo rol NO tiene el permiso "ver usuarios", **When** abre el menú,
   **Then** no ve la opción de gestión de usuarios.
3. **Given** un usuario cuyo rol NO tiene el permiso requerido para una acción, **When** accede
   directamente a esa acción, **Then** el sistema responde con acceso denegado (403) y no ejecuta
   ningún cambio.
4. **Given** un rol al que el Administrador acaba de quitar un permiso, **When** un usuario de ese
   rol intenta en su siguiente solicitud la acción asociada, **Then** es rechazado (403) sin
   necesidad de volver a iniciar sesión.
5. **Given** un rol al que el Administrador acaba de otorgar un permiso, **When** un usuario de ese
   rol recarga la página, **Then** ve la nueva opción de menú y puede ejecutar la acción.

---

### User Story 3 - Gestión de cuentas de usuario por el Administrador (Priority: P2)

El Administrador crea cuentas de usuario, edita sus datos, les asigna o cambia su único rol,
restablece su contraseña, y las desactiva o reactiva. Una cuenta desactivada no puede iniciar
sesión y pierde de inmediato cualquier sesión abierta.

**Why this priority**: Permite operar el sistema con usuarios reales sin depender del seeder; es
necesaria antes de los módulos de Matrícula y Gestión Académica.

**Independent Test**: Iniciar sesión como Administrador, crear un usuario con rol Docente, iniciar
sesión con él, cambiarle el rol, desactivarlo y verificar que ya no puede iniciar sesión.

**Acceptance Scenarios**:

1. **Given** el Administrador, **When** crea un usuario con nombres, apellidos, nombre de usuario,
   correo (opcional), contraseña inicial y un rol, **Then** el usuario queda activo y puede iniciar
   sesión.
2. **Given** el Administrador, **When** intenta crear un usuario con un nombre de usuario o correo
   ya registrado, **Then** el sistema rechaza el registro indicando el campo duplicado.
3. **Given** un usuario existente, **When** el Administrador cambia su rol, **Then** a partir de la
   siguiente solicitud el usuario tiene exactamente los permisos del nuevo rol.
4. **Given** un usuario activo, **When** el Administrador lo desactiva, **Then** ese usuario no
   puede iniciar sesión (ve un mensaje de cuenta inactiva) y, si tenía una sesión abierta, esta
   deja de ser válida en su siguiente solicitud.
5. **Given** un usuario desactivado, **When** el Administrador lo reactiva, **Then** puede volver a
   iniciar sesión con su contraseña.
6. **Given** el Administrador, **When** restablece la contraseña de un usuario, **Then** la
   contraseña anterior deja de funcionar y la nueva permite iniciar sesión.
7. **Given** un usuario sin el permiso de gestión de usuarios, **When** intenta crear, editar o
   desactivar una cuenta, **Then** es rechazado (403).

---

### User Story 4 - Gestión de permisos y asignación a roles (Priority: P2)

El Administrador consulta el catálogo de permisos, crea nuevos permisos, edita su nombre visible y
descripción, y define qué permisos tiene cada uno de los roles marcando o desmarcando permisos.

**Why this priority**: Hace que la política de acceso sea configurable con datos, sin cambios de
código, como exige la constitución.

**Independent Test**: Como Administrador, quitar al rol Director el permiso "ver usuarios", iniciar
sesión como Director y comprobar que la opción desaparece y el acceso directo devuelve 403; luego
restaurarlo.

**Acceptance Scenarios**:

1. **Given** el Administrador, **When** crea un permiso con un identificador único, nombre y
   descripción, **Then** el permiso aparece en el catálogo y puede asignarse a cualquier rol.
2. **Given** un permiso existente, **When** el Administrador edita su nombre o descripción,
   **Then** los cambios se guardan y el identificador permanece sin cambios.
3. **Given** el Administrador, **When** intenta crear un permiso con un identificador ya existente,
   **Then** el sistema lo rechaza.
4. **Given** un rol, **When** el Administrador modifica su conjunto de permisos y guarda, **Then**
   todos los usuarios de ese rol quedan sujetos al nuevo conjunto desde su siguiente solicitud.
5. **Given** un usuario sin el permiso de gestión de permisos o roles, **When** intenta modificar
   permisos o asignaciones, **Then** es rechazado (403).

---

### User Story 5 - Datos iniciales de roles y permisos (Priority: P1)

Al preparar una instalación nueva, un proceso de carga inicial crea los 5 roles (Administrador,
Director, Docente, Padre de Familia, Alumno), el catálogo de permisos base del módulo, sus
asignaciones iniciales y una cuenta de Administrador inicial para poder entrar al sistema.

**Why this priority**: Sin estos datos nadie puede iniciar sesión ni administrar el sistema en una
instalación nueva.

**Independent Test**: Reconstruir la base de datos desde cero, ejecutar la carga inicial y verificar
que existen los 5 roles, los permisos base, las asignaciones esperadas y que el Administrador
inicial puede iniciar sesión.

**Acceptance Scenarios**:

1. **Given** una base de datos vacía, **When** se ejecuta la carga inicial, **Then** existen
   exactamente los 5 roles definidos con sus permisos base.
2. **Given** la carga inicial ya ejecutada, **When** se ejecuta nuevamente, **Then** no se duplican
   roles, permisos ni asignaciones.
3. **Given** la carga inicial ejecutada, **When** el Administrador inicial inicia sesión, **Then**
   tiene acceso a la gestión de usuarios, permisos y roles.

---

### User Story 6 - Punto de extensión para filtros de pertenencia (Priority: P3)

El sistema dispone de un mecanismo reutilizable, adicional a los permisos por rol, para restringir
en el futuro los registros que un usuario puede ver según su relación con esos datos (por ejemplo,
un padre solo verá a sus propios hijos; un docente, solo sus secciones). El mecanismo es
fail-closed: si un tipo de registro requiere pertenencia y no tiene regla registrada, el acceso se
deniega. En este módulo el mecanismo queda disponible y probado, pero aún no se aplica a datos
reales porque estos los crearán los módulos de Matrícula y Gestión Académica.

**Why this priority**: No aporta valor visible hoy, pero evita rediseñar la autorización cuando
lleguen los módulos que manejan datos de estudiantes y del chatbot (Principio VI).

**Independent Test**: Con un tipo de registro de prueba y una regla de pertenencia de prueba,
verificar que al consultar a través del mecanismo solo se devuelven los registros que cumplen la
regla para el usuario autenticado, y que un tipo de registro que requiere pertenencia pero no
tiene regla registrada es rechazado con acceso denegado (403).

**Acceptance Scenarios**:

1. **Given** una regla de pertenencia de prueba registrada para un tipo de registro, **When** un
   usuario consulta ese tipo de registro a través del mecanismo, **Then** solo recibe los registros
   que la regla le permite.
2. **Given** un tipo de registro marcado como que requiere pertenencia y sin regla registrada,
   **When** un usuario (con el permiso RBAC correspondiente) lo consulta a través del mecanismo,
   **Then** el sistema responde con acceso denegado (403) y no devuelve ningún registro.
3. **Given** un tipo de registro que no requiere pertenencia (p. ej. un catálogo de
   configuración), **When** se consulta, **Then** solo aplica la verificación de permisos RBAC.
4. **Given** la regla de pertenencia, **When** se evalúa, **Then** no depende del nombre del rol
   sino del usuario autenticado y sus relaciones con los datos.

---

### Edge Cases

- **Último Administrador**: el sistema impide desactivar, o cambiar de rol, a la última cuenta
  activa que conserve los permisos de gestión de usuarios y de roles, para evitar que la
  instalación quede sin administración.
- **Auto-bloqueo**: un Administrador no puede desactivar su propia cuenta ni quitar a su propio rol
  los permisos de gestión de roles y permisos si con ello nadie activo los conservaría.
- **Sesión activa de cuenta desactivada**: la sesión deja de ser válida en la siguiente solicitud y
  el usuario es redirigido al inicio de sesión con un mensaje de cuenta inactiva.
- **Cambio de permisos con sesión abierta**: los permisos se evalúan en cada solicitud; no se
  requiere cerrar sesión para que un cambio surta efecto.
- **Intentos repetidos de inicio de sesión**: tras 5 intentos fallidos consecutivos para un mismo
  identificador desde la misma dirección IP en 1 minuto, se bloquean temporalmente nuevos intentos
  de esa combinación identificador + IP durante 1 minuto. Un intento con credenciales correctas
  sobre una cuenta inactiva también cuenta como fallido (se rechaza el acceso), de modo que no
  permite repetir verificaciones de contraseña sin tope.
- **Usuario sin rol**: no puede existir; crear o editar un usuario sin rol es rechazado.
- **Identificador de acceso ambiguo**: un nombre de usuario no puede tener formato de correo, para
  que un mismo texto nunca coincida con dos cuentas distintas.
- **Rol sin permisos**: un usuario de un rol sin permisos puede iniciar sesión, pero solo ve su
  página de inicio y la opción de cerrar sesión.
- **Mayúsculas en el identificador**: el correo y el nombre de usuario se comparan sin distinguir
  mayúsculas de minúsculas.
- **Regla de pertenencia faltante**: si un módulo futuro expone un tipo de registro que requiere
  pertenencia y olvida registrar su regla, toda consulta a ese tipo es rechazada (403) en lugar de
  devolver datos sin filtrar; el error es visible en pruebas y no filtra información.
- **Regla que no aplica al usuario**: si la regla existe pero el usuario no tiene relación con
  ningún registro (p. ej. un padre sin hijos vinculados), la consulta devuelve un resultado vacío,
  no un error.
- **Acceso directo a un registro ajeno**: solicitar un registro concreto que requiere pertenencia y
  no pertenece al usuario se rechaza con acceso denegado (403).

## Requirements *(mandatory)*

### Functional Requirements

**Autenticación**

- **FR-001**: El sistema MUST permitir iniciar sesión con correo electrónico o nombre de usuario
  más contraseña.
- **FR-002**: El sistema MUST rechazar el inicio de sesión con credenciales inválidas mostrando un
  mensaje genérico que no revele si el identificador existe.
- **FR-003**: El sistema MUST rechazar el inicio de sesión de cuentas desactivadas, aunque la
  contraseña sea correcta.
- **FR-004**: El sistema MUST invalidar la sesión de una cuenta desactivada en su siguiente
  solicitud.
- **FR-005**: El sistema MUST permitir cerrar sesión y redirigir al inicio de sesión cualquier
  acceso no autenticado a páginas protegidas.
- **FR-006**: El sistema MUST limitar los intentos fallidos de inicio de sesión (5 por combinación
  de identificador + dirección IP por minuto) y bloquear temporalmente los siguientes. Cuenta como
  intento fallido toda credencial inválida y también el login con credenciales correctas de una
  cuenta inactiva; solo los logins exitosos no cuentan y limpian el contador. No se añade un
  `throttle` por IP a nivel de ruta (bloquearía la prueba de carga desde una sola IP y a colegios
  con IP pública compartida).
- **FR-007**: El sistema MUST almacenar las contraseñas solo de forma irreversible (nunca en texto
  plano) y exigir una longitud mínima de 8 caracteres.
- **FR-008**: El sistema MUST cerrar automáticamente las sesiones tras 120 minutos de inactividad.

**Roles y permisos (RBAC)**

- **FR-009**: Cada usuario MUST tener exactamente un rol asignado.
- **FR-010**: El sistema MUST mantener un catálogo de roles, un catálogo de permisos y una
  asignación muchos a muchos entre roles y permisos, todos almacenados como datos.
- **FR-011**: Toda decisión de autorización MUST resolverse consultando, en el momento de la
  solicitud, si el rol del usuario tiene asignado el permiso requerido. Está prohibido decidir el
  acceso comparando el nombre del rol.
- **FR-012**: El sistema MUST responder con acceso denegado (403) cualquier intento de ejecutar una
  acción sin el permiso requerido, sin efectuar cambios.
- **FR-013**: El menú de navegación MUST mostrar únicamente las opciones cuyo permiso requerido
  tiene el rol del usuario autenticado.
- **FR-014**: Cada permiso MUST tener un identificador único e inmutable, un nombre visible y una
  descripción.
- **FR-015**: Los cambios en permisos de un rol o en el rol de un usuario MUST surtir efecto a
  partir de la siguiente solicitud del usuario afectado.

**Administración**

- **FR-016**: Un usuario con los permisos correspondientes (inicialmente, el Administrador) MUST
  poder crear, listar, editar, desactivar y reactivar cuentas de usuario.
- **FR-017**: Un usuario con el permiso correspondiente MUST poder asignar o cambiar el rol de un
  usuario.
- **FR-018**: Un usuario con el permiso correspondiente MUST poder restablecer la contraseña de otro
  usuario.
- **FR-019**: Un usuario con los permisos correspondientes MUST poder crear permisos y editar su
  nombre y descripción.
- **FR-020**: Un usuario con el permiso correspondiente MUST poder modificar el conjunto de permisos
  asignados a cada rol.
- **FR-021**: El sistema MUST validar la unicidad del nombre de usuario y del correo (cuando se
  proporcione), sin distinguir mayúsculas de minúsculas.
- **FR-022**: El sistema MUST impedir operaciones que dejen la instalación sin al menos una cuenta
  activa con los permisos de gestión de usuarios y de roles/permisos.

**Datos iniciales**

- **FR-023**: El sistema MUST incluir una carga inicial repetible (sin duplicados) que cree los 5
  roles (Administrador, Director, Docente, Padre de Familia, Alumno), los permisos base del módulo
  y sus asignaciones, según la tabla de permisos base de la sección Assumptions.
- **FR-024**: La carga inicial MUST crear una cuenta de Administrador inicial cuyas credenciales
  provienen de la configuración del entorno, no de valores fijos en el código.

**Filtros de pertenencia (punto de extensión)**

- **FR-025**: El sistema MUST ofrecer un mecanismo reutilizable para aplicar reglas de pertenencia
  a las consultas de datos, que restrinja los resultados según el usuario autenticado y sus
  relaciones con los datos.
- **FR-026**: Las reglas de pertenencia MUST poder registrarse por tipo de registro sin modificar el
  mecanismo, y MUST NOT basarse en el nombre del rol.
- **FR-027**: El mecanismo MUST ser fail-closed: cada tipo de registro MUST declarar si requiere
  pertenencia; si la requiere y no tiene regla registrada, el sistema MUST denegar el acceso (403)
  y MUST NOT devolver registros sin filtrar. Los tipos que no requieren pertenencia (p. ej.
  catálogos de configuración) solo se sujetan a RBAC.
- **FR-028**: El mecanismo MUST contar con pruebas que demuestren su funcionamiento usando una regla
  y datos de prueba, incluida la denegación (403) de un tipo de registro que requiere pertenencia
  sin regla registrada; no se implementan reglas reales en este módulo.

### Key Entities

- **Usuario**: persona que accede al sistema. Atributos: nombres, apellidos, nombre de usuario
  (único), correo electrónico (opcional, único), contraseña protegida, estado (activo/inactivo),
  rol asignado (exactamente uno).
- **Rol**: perfil de acceso. Atributos: nombre (único), descripción. Se relaciona con muchos
  usuarios y con muchos permisos.
- **Permiso**: capacidad para ejecutar una acción o ver una sección. Atributos: identificador
  (único e inmutable), nombre visible, descripción.
- **Rol_Permiso**: asignación de un permiso a un rol (relación muchos a muchos). Un par rol–permiso
  aparece como máximo una vez.
- **Regla de pertenencia**: definición (no persistida como dato de negocio en este módulo) que,
  para un tipo de registro, determina qué registros puede ver un usuario según sus relaciones.
  Cada tipo de registro declara si requiere pertenencia; si la requiere y no tiene regla, el
  acceso se deniega.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: El 100% de los intentos de inicio de sesión con credenciales inválidas o con cuentas
  desactivadas es rechazado en las pruebas automatizadas.
- **SC-002**: El 100% de las acciones protegidas del módulo tiene al menos una prueba de acceso
  permitido y una de acceso denegado (403).
- **SC-003**: Para cada uno de los 5 roles, las opciones de menú visibles coinciden al 100% con los
  permisos asignados a ese rol.
- **SC-004**: Un cambio en los permisos de un rol se refleja en los usuarios de ese rol en su
  siguiente acción, sin cerrar sesión, en el 100% de los casos probados.
- **SC-005**: Una revisión del código del módulo encuentra cero decisiones de acceso basadas en el
  nombre del rol.
- **SC-006**: Con una prueba de carga k6 de hasta **1000 usuarios virtuales** (rampa escalonada
  100 → 300 → 600 → 1000), donde cada usuario inicia sesión una sola vez con su propia cuenta y
  luego realiza consultas autenticadas con pausas de 10 a 30 s entre ellas, sobre un servidor de
  2 vCPU / 4 GB con la pila de producción (nginx + PHP-FPM, OPcache, Redis, MySQL 8): el 95% de los
  inicios de sesión se completa en menos de 3,5 s, el 95% de las consultas autenticadas en menos de
  1,5 s, y la tasa de solicitudes fallidas es menor al 1%. Los umbrales del login son mayores que
  los de las consultas porque el hash bcrypt (costo 12, ~250 ms de CPU por intento) es deliberadamente
  costoso y es el recurso limitante con 2 vCPU; por eso las rampas escalonan los inicios de sesión.
- **SC-007**: El Administrador puede crear una cuenta nueva y asignarle un rol en menos de 1 minuto.
- **SC-008**: Una instalación nueva queda operativa (5 roles, permisos base y Administrador inicial
  capaz de iniciar sesión) ejecutando únicamente la carga inicial documentada.

## Assumptions

- **Identificador de acceso**: todos los usuarios tienen nombre de usuario obligatorio; el correo
  es opcional porque muchos alumnos de primaria no tienen correo propio.
- **Recuperación de contraseña**: la recuperación autoservicio por correo y el cambio de contraseña
  por el propio usuario quedan fuera de alcance; el Administrador restablece contraseñas.
- **Reactivación**: se incluye reactivar cuentas como complemento natural de desactivarlas; no se
  eliminan cuentas físicamente, solo se desactivan.
- **Roles**: los 5 roles se crean por carga inicial. Crear o eliminar roles desde la interfaz queda
  fuera de alcance de este módulo; el modelo de datos lo permite sin cambios de código.
- **Permisos**: no se eliminan desde la interfaz (solo se crean y editan). Un permiso nuevo creado
  desde la interfaz solo tiene efecto cuando alguna funcionalidad lo requiere; los módulos futuros
  añadirán sus propios permisos base mediante su carga inicial.
- **Permisos base del módulo** (identificadores ilustrativos; el plan definirá los finales):

  | Permiso | Administrador | Director | Docente | Padre de Familia | Alumno |
  |---------|:-------------:|:--------:|:-------:|:----------------:|:------:|
  | Ver usuarios | ✔ | ✔ | | | |
  | Crear usuarios | ✔ | | | | |
  | Editar usuarios (incluye cambiar rol y restablecer contraseña) | ✔ | | | | |
  | Desactivar/reactivar usuarios | ✔ | | | | |
  | Ver roles y permisos | ✔ | ✔ | | | |
  | Crear/editar permisos | ✔ | | | | |
  | Asignar permisos a roles | ✔ | | | | |

- **Página de inicio**: todo usuario autenticado accede a una página de inicio y a cerrar sesión
  sin permiso específico; el contenido de esa página lo definirán los módulos posteriores.
- **Filtros de pertenencia**: el comportamiento por defecto es denegar (FR-027). Los módulos de
  Matrícula, Gestión Académica, Asistencia, Evaluación y Chatbot MUST declarar qué tipos de
  registro requieren pertenencia y registrar sus reglas; de lo contrario sus consultas serán
  rechazadas.
- **Cuentas de Alumno**: crear una cuenta con rol Alumno es opcional y lo decide el Administrador al
  gestionar usuarios; no se crea automáticamente para cada estudiante matriculado. En primaria se
  espera que el Padre de Familia acceda en nombre del estudiante (por su edad); en secundaria es
  más común habilitar la cuenta del propio estudiante. Esto no modifica los 5 roles definidos en la
  Constitución.
- **Auditoría**: no se incluye bitácora de auditoría detallada en este módulo; basta con registrar
  fechas de creación y modificación.
- **Autenticación de dos factores y SSO**: fuera de alcance del MVP.
- **Limitación de intentos por identificador + IP (limitación conocida)**: en redes con NAT
  compartido (p. ej. el WiFi institucional), muchos usuarios salen por la misma IP, por lo que la
  IP no aporta protección adicional frente a limitar solo por identificador. Se acepta como
  limitación conocida del MVP; no se resuelve en este módulo.
- **Dependencias**: este es el primer módulo; no depende de otros módulos del SGE.
