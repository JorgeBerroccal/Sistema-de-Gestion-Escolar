# Patrones de diseño

## Objetivo

Documentar los patrones de diseño que existen en el código del SGE, dónde se usan, qué problema resuelven y con qué archivos se implementan. Solo se listan patrones presentes en el repositorio; los que se propusieron en el diseño inicial y no se construyeron figuran aparte.

## Contexto

- **Estilo:** monolito modular (Laravel 13).
- **Patrón arquitectónico:** MVC con capa de servicios, por dominio (ADR-002).
- **Base:** [componentes arquitectónicos](../../02-arquitectura-software/componentes-arquitectonicos.md) y [nivel 3 del modelo C4](../../04-modelo-c4/Nivel3-Diagrama-de-Componentes.md).

## Patrones aplicados

| Patrón | Dónde se usa | Problema que resuelve | Se implementa con | Archivos |
|---|---|---|---|---|
| **Puerto y adaptador (Adapter)** | Asistente → proveedor de IA | El proveedor debe poder cambiarse por configuración y sustituirse por uno falso sin gastar cuota ni salir a la red (DA02, RC18, constitución 2.3.0) | Interfaz `ProveedorIA` con `ProveedorFalso` y `ProveedorOpenRouter`; el contenedor elige por `ASISTENTE_PROVEEDOR` | `app/Asistente/Proveedores/ProveedorIA.php`, `ProveedorFalso.php`, `ProveedorOpenRouter.php`, `AppServiceProvider.php` |
| **Service Layer** | Matrícula, Asistencia, Evaluación, Gestión Académica, Asistente | Evitar que las reglas queden repartidas en componentes y vistas (DA07, DA09) | Una o varias clases de servicio por dominio | `EvaluacionService.php`, `AsistenciaService.php`, `MatriculaService.php`, `AlumnoService.php`, `AsignacionService.php`, `AnioEscolarService.php`, `AsistenteService.php` |
| **Strategy con registro por configuración** | Autorización → pertenencia de datos | Cada modelo decide de forma distinta qué registros ve cada usuario, y un modelo sin regla debe denegarse (DA01, ADR-011) | Interfaz `ReglaPertenencia` con una clase por modelo; `RegistroReglasPertenencia` la resuelve desde `config/pertenencia.php`; trait `RequierePertenencia` | `app/Autorizacion/Pertenencia/*`, `config/pertenencia.php`, `app/Autorizacion/Pertenencia/Reglas/*` |
| **Query scope** (patrón de Eloquent) | Modelos con pertenencia | Aplicar el filtro de pertenencia a cualquier consulta con una sola llamada | Scope `visiblePara($usuario)` en el trait | `RequierePertenencia.php` |
| **Clase de cálculo pura (Domain Service)** | Evaluación → promedios | Calcular promedios y etiquetas con una escala configurable y probarlos sin base de datos (DA04, DA07) | Clase sin acceso a datos, creada con `CalculoPromedios::deEscala($escala)` | `CalculoPromedios.php`, `tests/Unit/Evaluacion/CalculoPromediosTest.php` |
| **Servicio con alcance de solicitud (Scoped)** | Evaluación | Leer la institución y su escala una sola vez por solicitud | `scoped(EvaluacionService::class)` en el contenedor | `AppServiceProvider.php` |
| **Singleton del contenedor** | Proveedor de IA y registro de reglas | Una única instancia configurada | `singleton(ProveedorIA::class, …)` y `singleton(RegistroReglasPertenencia::class)` | `AppServiceProvider.php` |
| **Excepción de dominio** | Todos los servicios | Distinguir un incumplimiento de regla de un error técnico, con mensajes sin datos personales (Ley 29733) | Una clase por dominio con la lista de mensajes | `EvaluacionIncumplida.php`, `MatriculaIncumplida.php`, `AsistenciaIncumplida.php`, `ReglaAcademicaIncumplida.php`, `ReglaPertenenciaFaltante.php` |
| **Rasgo reutilizable (Trait)** | Gestión Académica | Compartir desactivar, reactivar y eliminar entre pantallas de catálogos | Trait de Livewire | `app/Livewire/Academico/Concerns/CambiaEstado.php` |

## Cómo funciona cada patrón

| Patrón | En pocas palabras |
|---|---|
| Puerto y adaptador | `AsistenteService` dice `responder($mensajes)`; el adaptador configurado lo traduce a la llamada HTTP de OpenRouter o devuelve una respuesta determinista y guarda lo recibido para las pruebas. |
| Service Layer | El controlador dice `guardarNotas(...)`; el servicio aplica todas las reglas y escribe en una transacción. |
| Strategy con registro | `Nota::query()->visiblePara($usuario)` busca la regla de `Nota` en `config/pertenencia.php` y la aplica; si no existe, lanza `ReglaPertenenciaFaltante` (403). |
| Clase de cálculo pura | `CalculoPromedios` recibe la escala y responde si un valor es válido, el promedio ponderado y la letra más cercana; no conoce MySQL. |
| Scoped | La institución y la escala se consultan la primera vez que un servicio las pide y se reutilizan durante esa solicitud. |

## Beneficios

- Cambiar de proveedor de IA solo exige implementar `ProveedorIA` y registrarlo; las pruebas de la suite nunca salen a la red.
- Una regla de pertenencia nueva es una clase y una línea de configuración; el mecanismo no cambia (spec 001, FR-026).
- Una escala nueva se agrega como datos, sin tocar `CalculoPromedios`, mientras use valores numéricos o letras con equivalentes.
- Las reglas de negocio se prueban llamando a los servicios con Pest.

## Patrones del diseño inicial que no se construyeron

| Patrón propuesto | Estado real | Qué hay en su lugar |
|---|---|---|
| Strategy `Escala` (`EscalaLiteral`, `EscalaVigesimal`) | No existe | La escala es un dato (`escalas_calificacion`) interpretado por `CalculoPromedios`. |
| Adapter `AsistenteClaude` y `AsistenteSimulado` | No existen | `ProveedorOpenRouter` y `ProveedorFalso` detrás de `ProveedorIA`. |
| Observer (`NotaRegistrada`, `RegistrarEnBitacora`) | No existe | No hay eventos, listeners ni auditoría (ADR-010). |
| Decorator `PermisosEnCache` | No existe | `Usuario::tienePermiso()` guarda los permisos en memoria durante la solicitud; no hay caché en Redis. |
| Facade `ConsultasEvaluacion` y `ConsultasAsistencia` | No existen | El asistente usa `ConsultaNotas` y `ResumenAsistencia`, las mismas consultas de las pantallas. |
| Circuit Breaker (previsto) | No existe | Ante un fallo, `ProveedorNoDisponible` produce un mensaje comprensible, y `LimiteSolicitudes` protege el cupo; no se corta el acceso tras varios errores. |
| Factory de boletas, Chain of Responsibility de matrícula (previstos) | No existen | Boleta de un solo formato; validaciones de matrícula dentro de `MatriculaService`. |

## Cómo se verificó

Se listó el contenido de `app/` y se leyeron `AppServiceProvider`, `ProveedorIA`, `RequierePertenencia`, `RegistroReglasPertenencia` y `CalculoPromedios`. Se buscaron, sin resultados, `app/Events`, `app/Listeners`, `app/Jobs` y `Cache::` en `app/`.
