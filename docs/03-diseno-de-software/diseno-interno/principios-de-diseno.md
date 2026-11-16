# Principios de diseño (SOLID)

## Objetivo

Documentar cómo se aplican los principios SOLID en el código del SGE, con ejemplos que existen en el repositorio y sus límites, para que cada módulo se programe de forma uniforme y las reglas de negocio estén en un solo lugar.

## Contexto

- **Estilo:** monolito modular (Laravel 13).
- **Patrón arquitectónico:** MVC con capa de servicios, por dominio.
- **Módulos de referencia:** Asistente Virtual (integración con un servicio externo y orquestación), Autorización (reglas de pertenencia) y Evaluación (cálculo con escala configurable).

## SOLID en el código construido

| Principio | Dónde se aplica | Cómo se cumple en el SGE | Archivos |
|---|---|---|---|
| **S** · Responsabilidad única | Asistente y Evaluación | `AsistenteService` solo orquesta: delega la elección del alumno en `ResolverAlumno`, el cupo en `LimiteSolicitudes`, la disponibilidad en `Disponibilidad`, el texto del contexto en `ConstructorContexto` y la llamada en `ProveedorIA`. En Evaluación, `NotasController` atiende la solicitud, `EvaluacionService` aplica las reglas de escritura, `CalculoPromedios` calcula y `ConsultaNotas` lee. | `AsistenteService.php`, `ResolverAlumno.php`, `LimiteSolicitudes.php`, `Disponibilidad.php`, `ConstructorContexto.php`, `NotasController.php`, `EvaluacionService.php`, `CalculoPromedios.php`, `ConsultaNotas.php` |
| **O** · Abierto/cerrado | Pertenencia, escalas y proveedores | Una regla de pertenencia nueva es una clase que implementa `ReglaPertenencia` más una línea en `config/pertenencia.php`, sin tocar el mecanismo. Una escala nueva se agrega como datos. Un proveedor de IA nuevo implementa `ProveedorIA`; hay que **agregar su caso en `AppServiceProvider`** y su configuración (por eso se cumple con una modificación mínima de registro, no sin ninguna). | `ReglaPertenencia.php`, `RegistroReglasPertenencia.php`, `config/pertenencia.php`, `ProveedorIA.php`, `AppServiceProvider.php`, `docs/asistente.md` (plan B de Gemini) |
| **L** · Sustitución de Liskov | Reglas y proveedores | Todas las reglas de pertenencia cumplen el mismo contrato (`aplicar` y `permite`) y el mecanismo las trata igual. `ProveedorFalso` y `ProveedorOpenRouter` devuelven una `RespuestaIA` y lanzan `ProveedorNoDisponible` ante fallos; **difieren en `admiteDatosReales()`**, que es parte declarada del contrato y no un incumplimiento. | `ReglaPertenencia.php`, `Reglas/*`, `ProveedorFalso.php`, `ProveedorOpenRouter.php`, `tests/Feature/Asistente/CambioDeProveedorTest.php` |
| **I** · Segregación de interfaces | Interfaces del dominio | `ProveedorIA` tiene tres métodos (`responder`, `admiteDatosReales`, `nombre`) y `ReglaPertenencia` dos (`aplicar`, `permite`); ningún implementador está obligado a métodos que no usa. | `ProveedorIA.php`, `ReglaPertenencia.php` |
| **D** · Inversión de dependencias | Asistente | `AsistenteService` recibe `ProveedorIA` (la interfaz) por el constructor y el contenedor decide qué adaptador inyectar según `config('asistente.proveedor')`. | `AsistenteService.php`, `AppServiceProvider.php` |

## Límites reconocidos

| Punto | Situación |
|---|---|
| Servicios concretos | `NotasController`, `BoletaController` y los componentes Livewire dependen de clases concretas (`EvaluacionService`, `BoletaService`), no de interfaces. Solo el proveedor de IA y las reglas de pertenencia están detrás de una interfaz. |
| Localizador de servicios | El trait `RequierePertenencia` obtiene el registro con `app(RegistroReglasPertenencia::class)` en vez de recibirlo por inyección. |
| Catálogos de Gestión Académica | Los componentes Livewire de niveles, grados, secciones, cursos e institución guardan con Eloquent sin pasar por un servicio (ver [patrón arquitectónico](../../02-arquitectura-software/patron-arquitectonico.md#reglas-del-patrón)). |
| Verificación | No hay pruebas `arch()`; el cumplimiento de estas prácticas depende de pruebas por módulo y de la revisión. |

## Qué pasaría sin estas prácticas en el SGE

| Situación real | Sin ellas | Con lo construido |
|---|---|---|
| Un colegio usa escala vigesimal y otro literal | `if` por tipo de escala repartidos en controladores y vistas | Una escala es una fila de datos; `CalculoPromedios` la interpreta sin nombrar códigos |
| El modelo gratuito del proveedor rota o se agota | Cambiar código del servicio del asistente | Cambiar `ASISTENTE_OPENROUTER_MODELO` o el respaldo en el `.env` y desplegar con `--reconfigurar` |
| La suite no debe gastar cuota ni salir a la red | Comentar código o crear una rama especial | `phpunit.xml` fija `ASISTENTE_PROVEEDOR=falso` y se usa `Http::preventStrayRequests()` |
| Un modelo nuevo con datos de alumnos olvida su filtro | El modelo devolvería todos los registros | Un modelo con `RequierePertenencia` sin regla registrada se deniega (403) |
| Cambia la regla del promedio | Buscarla en controladores, vistas y consultas | Está en `CalculoPromedios` |

## Relación con el patrón MVC y los patrones de diseño

| Principio | Lo cumple en el proyecto |
|---|---|
| Responsabilidad única | La separación en controlador, servicio, clase de cálculo y modelo; `AsistenteService` como orquestador |
| Abierto/cerrado y Liskov | Puerto y adaptador (`ProveedorIA`) y Strategy con registro (`ReglaPertenencia`) |
| Segregación de interfaces | Las interfaces pequeñas `ProveedorIA` y `ReglaPertenencia` |
| Inversión de dependencias | El contenedor de servicios de Laravel, que inyecta el proveedor de IA |
