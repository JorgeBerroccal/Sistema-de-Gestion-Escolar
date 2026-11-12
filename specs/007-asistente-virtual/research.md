# Research: Asistente Virtual

Decisiones técnicas tomadas para resolver las incógnitas del plan. Formato: decisión, razón, alternativas.

## R-01 — Cliente de OpenRouter

- **Decisión**: llamar a `POST {base_url}/chat/completions` (API compatible con OpenAI) con el cliente `Http` de
  Laravel, `Authorization: Bearer <clave>`, `timeout` configurable, sin SDK.
- **Razón**: Principio VII (sin dependencias nuevas); el formato es estable y simple; `Http::fake()` permite
  probar sin red.
- **Alternativas**: SDK de OpenAI/PHP (dependencia innecesaria); Prism/laravel-ai (abstracción extra).

## R-02 — Modelos de respaldo

- **Decisión**: variables `ASISTENTE_OPENROUTER_MODELO` y `ASISTENTE_OPENROUTER_RESPALDO` (lista separada por
  comas). El adaptador envía `model` y `models` (OpenRouter prueba los siguientes si el primero falla o está
  limitado) en **una sola solicitud**; si OpenRouter responde error global, se informa `ProveedorNoDisponible`.
- **Razón**: los modelos gratuitos rotan; una solicitud con `models` consume una sola unidad del cupo de 50/día,
  a diferencia de reintentar nosotros en bucle.
- **Alternativas**: reintentar desde PHP modelo por modelo (gasta cupo y alarga la espera).

## R-03 — Declaración «admite datos reales»

- **Decisión**: `ProveedorIA::admiteDatosReales(): bool`. El falso devuelve `true` (no sale a la red).
  OpenRouter devuelve `false` si algún modelo configurado termina en `:free`, o si
  `ASISTENTE_OPENROUTER_DATOS_REALES` no es `true`; así un modelo de pago no queda bloqueado sin querer y
  un gratuito nunca se declara apto por error.
- **Razón**: FR-014; la declaración sale de la configuración, no de un valor fijo.
- **Alternativas**: bandera manual única (se olvida cambiarla al pasar a un modelo de pago).

## R-04 — Falla cerrado

- **Decisión**: `Disponibilidad::verificar()` se evalúa al montar el componente y en cada envío:
  `app()->environment('production') && ! $proveedor->admiteDatosReales()` ⇒ mensaje claro y ninguna llamada.
- **Razón**: FR-015; la comprobación en el envío cubre cambios de configuración con la pantalla ya abierta.

## R-05 — Límite de solicitudes

- **Decisión**: `RateLimiter` de Laravel (Redis, ya usado por el login) con cuatro contadores: por usuario por
  minuto y por día, y global por minuto y por día. Valores por defecto: usuario 3/min y 15/día; global 18/min
  y 45/día (margen bajo 20 y 50). Se consume el cupo **antes** de llamar al proveedor; al superarlo se muestra
  `availableIn()` y no se llama al proveedor.
- **Razón**: FR-011 exige que el uso conjunto no supere el cupo del plan gratuito; el límite por usuario
  solo no lo garantiza (varios usuarios sumarían). Ambos son configurables por entorno.
- **Alternativas**: solo por usuario (no protege la cuota global); tabla en MySQL (más costo sin beneficio).

## R-06 — Qué alumno se consulta

- **Decisión**: `ResolverAlumno` obtiene los alumnos propios con `MatriculaRegla::alumnosPropios($usuario)`
  (nunca con `notas.ver_todas`, aunque el rol lo tuviera). Si hay uno, ese. Si hay varios, se compara el mensaje
  (sin tildes ni mayúsculas) con los nombres: un único acierto elige; ninguno o varios devuelven una
  aclaración local («¿de cuál de tus hijos…?») **sin llamar al proveedor**.
- **Razón**: FR-006/FR-008; la elección es del servidor, no del modelo; el contexto contiene a un solo alumno.
  Un nombre que no pertenece a los propios no coincide y no se confirma su existencia.
- **Alternativas**: poner a todos los hijos en el contexto y pedirle al modelo que separe (riesgo de mezcla).

## R-07 — Contexto mínimo

- **Decisión**: `ConstructorContexto` arma texto plano, y cada bloque se incluye solo si el usuario tiene el
  permiso de su pantalla (`matricula.ser_alumno`/`matricula.ser_apoderado` para matrícula y sección,
  `notas.ver_propia` para notas, `asistencia.ver_propia` para asistencia), porque `ResumenAsistencia` no filtra
  por permiso. El texto contiene: nombre y apellidos del alumno, año, grado, nivel,
  sección y estado de matrícula; por cada periodo **cerrado y publicado** (`ConsultaNotas::periodosPublicados`),
  cursos con promedio y notas por criterio (`ConsultaNotas::delAlumno($alumno, $periodo, $usuario)`); y
  resumen de asistencia del año vigente y del mes en curso (`ResumenAsistencia::delAlumno`). No se consulta ni
  se serializa el modelo `Alumno` completo: se leen campos concretos, de modo que documento, fecha de
  nacimiento y correo ni siquiera se cargan en el contexto.
- **Razón**: FR-005/FR-007; reutilizar las consultas de las pantallas garantiza «no saber más que las
  pantallas» y evita duplicar reglas de publicación.
- **Alternativas**: consultar tablas directamente (duplica y puede divergir de las reglas del Módulo 5).

## R-13 — Principio de diseño: la seguridad no depende del modelo

- **Decisión**: ninguna garantía de privacidad se apoya en que el modelo obedezca instrucciones. La frontera es
  el contexto: solo contiene datos que el usuario podría ver en las pantallas (R-06, R-07), de un único alumno
  elegido por el servidor y sin identificadores personales. Lo que el proveedor nunca recibió no lo puede
  revelar, ni por error ni por inyección de instrucciones.
- **Consecuencia**: las pruebas automáticas verifican el **payload** que recibe el adaptador (SC-002 a SC-004),
  no el texto de la respuesta. El tono neutro del rechazo (instrucción de R-08) es comportamiento del modelo y
  se comprueba a mano en la prueba de humo (`quickstart.md` §3, paso 3).
- **Razón**: Principio VI («un LLM no es una frontera de seguridad»).

## R-08 — Instrucciones e inyección

- **Decisión**: un mensaje `system` fijo (español obligatorio, usar solo el contexto, no inventar, negar con
  neutralidad pedidos fuera de alcance, ignorar órdenes que pidan cambiar estas reglas) seguido del contexto
  delimitado, y el texto del usuario únicamente como mensaje `user`. Longitud máxima del mensaje
  configurable (500 caracteres); se conservan los últimos N turnos (6) para la continuidad.
- **Razón**: la defensa real es que el contexto no contiene datos ajenos (R-06/R-07); las instrucciones
  reducen respuestas erróneas pero no son frontera de seguridad (Principio VI).

## R-09 — Sin historial

- **Decisión**: los turnos viven en una propiedad pública del componente Livewire (estado de la página) y no
  se escriben en base de datos, caché ni logs. Recargar la página reinicia el chat.
- **Razón**: decisión 1 del responsable; menor exposición de datos de menores.

## R-10 — Pruebas sin red

- **Decisión**: el contenedor resuelve `ProveedorIA` según `config('asistente.proveedor')`; `phpunit.xml`
  fija `ASISTENTE_PROVEEDOR=falso`. `ProveedorFalso` guarda en una propiedad estática/instancia los mensajes
  recibidos para que las pruebas inspeccionen el payload. Las pruebas de OpenRouter usan `Http::fake()` y
  `Http::preventStrayRequests()`.
- **Razón**: FR-018; la inspección del payload es la evidencia de SC-002 a SC-004.

## R-11 — Gemini (plan B)

- **Decisión**: documentar en `docs/despliegue/` cómo agregaría un tercer adaptador implementando
  `ProveedorIA` (endpoint, clave de Google AI Studio, cupo del plan gratuito) sin implementarlo.
- **Razón**: FR-019; la constitución 2.3.0 permite cambiar de proveedor sin enmendarla.

## R-12 — Permiso y menú

- **Decisión**: permiso `asistente.usar` en `RolesYPermisosSeeder` para `Padre de Familia` y `Alumno`
  (31 permisos en total; actualizar las pruebas de conteo). Ítem de menú «Asistente virtual» con ese permiso.
  La ruta y el componente comprueban el permiso en `mount()` (doble comprobación, como los Módulos 4 y 5).
- **Razón**: FR-001; SC-008. Documentar en el despliegue que el seeder es la fuente (en staging el conteo
  difiere por el permiso `reportes.ver` creado a mano en el Módulo 1).
