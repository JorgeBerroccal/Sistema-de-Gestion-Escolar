# Asistente Virtual (Módulo 6)

Referencia funcional del módulo. Diseño y decisiones: [`specs/007-asistente-virtual/`](../specs/007-asistente-virtual/).

## Qué hace y qué no

Un chat en español, en **Asistente virtual** (menú), que responde sobre **notas y promedios, asistencia, matrícula y sección**
del alumno propio (Alumno) o de los hijos vinculados (Padre de Familia). Es de **solo consulta**: nunca modifica datos. No guarda
historial: la conversación vive solo en la pantalla y recargar la página la reinicia. Toda pantalla del chat muestra el aviso de
que las respuestas las genera una IA y pueden contener errores; para datos oficiales están las pantallas y la boleta.

Fuera de alcance: historial persistente, voz, adjuntos, acciones que modifiquen datos, ajuste fino del modelo, acceso para
Docente, Director y Administrador, y el adaptador de Gemini (plan B, abajo).

## Quién lo usa

El permiso **`asistente.usar`** (ítem de menú y ruta `/asistente`). Por defecto lo tienen Padre de Familia y Alumno. Abrirlo a otro
rol es solo asignarle el permiso en **Roles → Permisos**; no hay código por rol. Aun así, el asistente es siempre «de lo propio»:
con el permiso, un rol que ve todo (Director, Administrador) solo recibiría respuestas de los alumnos vinculados a su cuenta.

## Reglas de privacidad (Principios III y VI)

La seguridad **no depende del modelo**: depende de que el contexto que recibe el proveedor solo contenga datos permitidos. Lo que
nunca se envió no se puede revelar, ni por error ni por una instrucción maliciosa dentro del mensaje.

| Sí se envía | Nunca se envía |
|---|---|
| Nombres y apellidos del alumno elegido por el servidor | Documento de identidad, fecha de nacimiento, correos, ids |
| Año, grado, nivel, sección y estado de matrícula | Datos de otros alumnos o de apoderados |
| Notas y promedios de **periodos cerrados y publicados** (regla del Módulo 5) | Notas de periodos no cerrados, o reabiertos tras publicarse |
| Resumen de asistencia del año y del mes | |

- Cada bloque (notas, asistencia, matrícula) se incluye **solo si el usuario tiene el permiso de su pantalla**
  (`notas.ver_propia`, `asistencia.ver_propia`, `matricula.ver_propia`); el asistente nunca sabe más que las pantallas.
- Un padre con varios hijos: el servidor elige al hijo por su nombre en el mensaje; si no puede, pide aclarar (sin llamar al proveedor).
- Un nombre ajeno nunca coincide con los propios; no se confirma que exista.
- El contenido de las preguntas y respuestas **no se registra** (solo usuario, adaptador y resultado).
- Las instrucciones al modelo (responder siempre en español, solo con el contexto, rechazar con neutralidad lo ajeno) reducen errores,
  pero no son la frontera de seguridad. El tono del rechazo se comprueba a mano en la prueba de humo.

## Proveedor de IA y configuración

El asistente usa una **interfaz de proveedor con adaptadores intercambiables** (constitución 2.3.0), elegidos por variable de entorno:

| `ASISTENTE_PROVEEDOR` | Uso |
|---|---|
| `falso` (por defecto) | Desarrollo y pruebas; determinista; **nunca sale a la red**. La suite usa solo este (o `Http::fake()`). |
| `openrouter` | Staging y demo (modelo gratuito) y uso real con presupuesto (por ejemplo Claude Haiku a través de OpenRouter). |

Variables de OpenRouter (todas en el `.env` del entorno; ver `.env.example`):

| Variable | Significado |
|---|---|
| `OPENROUTER_API_KEY` | La clave. **Solo en el `.env`; nunca en el repositorio.** |
| `ASISTENTE_OPENROUTER_MODELO` | Modelo principal (obligatorio) |
| `ASISTENTE_OPENROUTER_RESPALDO` | Modelos de respaldo, separados por comas. Se envían en **una sola solicitud** (`models`), así no gastan cupo extra |
| `ASISTENTE_OPENROUTER_DATOS_REALES` | `true` solo con modelos de pago aptos para datos reales (por defecto `false`) |
| `ASISTENTE_TIMEOUT` | Segundos de espera al proveedor (20) |

**Los modelos gratuitos rotan**: si uno deja de existir o se satura, cambia `ASISTENTE_OPENROUTER_MODELO` y/o el respaldo en el `.env`
y vuelve a desplegar con `--reconfigurar`; no hay cambios de código. Se pueden consultar los modelos gratuitos vigentes en la lista de
OpenRouter (identificadores que terminan en `:free`).

### Datos reales y falla cerrado

Cada adaptador declara si admite datos reales. OpenRouter con algún modelo `:free` (o sin `ASISTENTE_OPENROUTER_DATOS_REALES=true`)
declara que **no**. Si `APP_ENV=production` y el adaptador no los admite, el asistente **se niega a funcionar** con un mensaje claro y no
llama al proveedor. Staging solo tiene datos ficticios, por eso funciona allí.

## Límites de solicitudes y cupo del plan gratuito

El plan gratuito de OpenRouter permite **50 solicitudes al día y 20 por minuto en total**. El sistema aplica cuatro contadores
(por usuario y globales, por minuto y por día) con valores por defecto 3/15 por usuario y 18/45 globales, configurables:
`ASISTENTE_LIMITE_USUARIO_MINUTO`, `ASISTENTE_LIMITE_USUARIO_DIA`, `ASISTENTE_LIMITE_GLOBAL_MINUTO`, `ASISTENTE_LIMITE_GLOBAL_DIA`.
Al superarlos, el usuario ve cuánto esperar y no se llama al proveedor. La aclaración, la ausencia de datos y los mensajes inválidos no
consumen cupo. Si el proveedor falla o se agota su cuota, el usuario ve un mensaje comprensible y el resto del sistema sigue funcionando.

**Nota del cupo**: el contador del sistema solo cuenta las solicitudes que hace el propio asistente. Otras personas o pruebas manuales
que usen la misma clave gastan cupo que el sistema no ve. Por eso se usan **claves de OpenRouter distintas por entorno**: una solo
para staging (`sge-staging`, con límite de 1 USD) y otra para pruebas locales con el modelo real. Cada clave vive solo en el `.env` de su entorno.

## Plan B: Gemini (documentado, sin implementar)

Si OpenRouter dejara de servir, se agregaría un tercer adaptador sin enmendar la constitución: una clase `ProveedorGemini` que implemente
`App\Asistente\Proveedores\ProveedorIA` (`responder()`, `admiteDatosReales()`, `nombre()`) contra la API de Google AI Studio (clave en
`GEMINI_API_KEY`; el plan gratuito de Gemini **no admite datos reales**), un caso `gemini` en `AppServiceProvider` y su configuración en
`config/asistente.php`. Todo lo demás (contexto, límites, falla cerrado, pruebas con el adaptador falso) no cambia. No se implementa en este módulo.

## Pruebas

Toda la suite del módulo usa `ProveedorFalso` (que guarda lo recibido para inspeccionarlo) o `Http::fake()`, y
`Http::preventStrayRequests()` está activo en todas las pruebas: una llamada HTTP sin simular falla. Las garantías de privacidad se
verifican sobre el **payload** que recibe el adaptador: ningún dato ajeno, ninguna nota no publicada y ningún identificador personal.
