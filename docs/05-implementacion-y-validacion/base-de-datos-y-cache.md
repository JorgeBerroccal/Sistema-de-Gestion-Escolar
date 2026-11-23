# Base de datos y estrategia de caché

Reúne lo que **ya existe** en el código y en la documentación; no agrega nada nuevo. Cada dato lleva su fuente. Lo que no existe se dice como tal.

## 1. Base de datos

MySQL 8 (contenedor `db`), 24 migraciones en `database/migrations/`. Las reglas de rendimiento de la constitución (sección «Reglas de Rendimiento», `.specify/memory/constitution.md`) exigen índice en toda clave foránea y en toda columna de búsqueda, filtro u orden; `with()` en toda relación usada en una vista; y todo listado paginado.

### Índices y unicidad

| Qué | Dónde | Para qué |
|---|---|---|
| Índice compuesto `criterios_orden (seccion_id, curso_id, periodo_id, orden)` | `2026_11_13_000002_create_criterios_evaluacion_table.php` | Leer el esquema de criterios de una sección, curso y periodo ya ordenado |
| Único `criterios_unico_nombre` en los mismos tres campos más `nombre` | ídem | Sin criterios repetidos |
| Único `(criterio_id, alumno_id)` e índice `alumno_id` en `notas` | `2026_11_13_000003_create_notas_table.php` | Una nota por alumno y criterio; guardar de nuevo reemplaza (`GuardarNotasTest`, «la base impide duplicados y el último guardado prevalece») |
| Índice `(usuario_id, seccion_id)` en asignaciones docentes | `2026_10_24_000009_create_asignaciones_docentes_table.php` | Filtro de pertenencia del docente |
| Índices `(seccion_id, estado)` y `(alumno_id, anio_id)` en `matriculas` | `2026_11_05_000004_create_matriculas_table.php` | Nóminas por sección y matrículas del alumno |
| Índices sobre `activo`, `nombres`, `apellidos` en usuarios; `activo` y `fecha_inicio` en años; `activo` en cursos | migraciones de usuarios, años escolares y cursos | Filtros y orden de los listados |
| Índice `usuario_id` en `alumnos` y `alumno_apoderado` | `tests/Feature/Rendimiento/OptimizacionesCargaTest.php` («alumnosPropios no usa UNION y se apoya en índices de usuario_id») lo verifica | Base de `MatriculaRegla::alumnosPropios` |
| Claves foráneas con `restrictOnDelete` casi siempre | las migraciones citadas | Sin borrados en cascada de datos académicos; `notas` sí cae con su criterio (`cascadeOnDelete`) |

### Columna generada con índice único

`matriculas.anio_activa_id` es una columna **generada y almacenada**: `IF(estado = 'matriculado', anio_id, NULL)`. Con el índice único `(alumno_id, anio_activa_id)`, la propia base impide dos matrículas activas del mismo alumno en un año, porque MySQL admite varios `NULL` en un índice único. Fuente: `2026_11_05_000004_create_matriculas_table.php`. Pruebas: `MatricularTest.php`, «la base impide dos matrículas activas del mismo alumno y año aunque se salte el servicio (concurrencia)» y «la columna generada marca el año solo mientras la matrícula está activa». Consecuencia anotada en la migración: la clave foránea de `anio_id` es `restrict`, porque MySQL no admite `CASCADE` ni `SET NULL` sobre la base de una columna generada.

### Transacciones

`DB::transaction` envuelve las operaciones de varios pasos de `MatriculaService` (matricular, matrícula masiva, corregir y cambiar de estado), `AlumnoService`, `AsistenciaService` y `AnioEscolarService` (`app/`). Las operaciones masivas son **todo o nada**: matrícula masiva (`MatricularTest`, «masiva: matricula a todos o a ninguno»), guardado de notas (`GuardarNotasTest`, «es todo o nada…»), asistencia (`AsistenciaServiceTest`), asignar varios cursos y crear varias secciones.

### Carga anticipada y paginación

- **Paginación:** `app/` tiene 16 llamadas a `paginate(`; p. ej. el listado de alumnos usa `paginate(15)` con orden determinista por apellidos, nombres e id (`app/Livewire/Matricula/Alumnos/Index.php`).
- **Carga anticipada:** relaciones con `with(` (p. ej. `with('tipoDocumento')` en ese mismo listado) en 35 puntos de `app/` (búsqueda de `with(` en `app/`).
- Optimizaciones de v0.7.0 verificadas por `OptimizacionesCargaTest`: `alumnosPropios` sin `UNION` y con índices; la institución y la escala se leen una sola vez por solicitud en `EvaluacionService`; las notas y la asistencia propias consultan los alumnos propios una sola vez por solicitud.

## 2. Redis y caché

Redis 7 (`phpredis`) en el servicio `redis`, con `--maxmemory` y política `volatile-lru` y sin persistencia (`--save ""`): `docker-compose.yml` (256 MB) y `docker-compose.prod.yml` (`REDIS_MAXMEMORY`, 64 MB por defecto).

| Uso | Estado | Fuente |
|---|---|---|
| Sesiones | **Sí:** `SESSION_DRIVER=redis`, duración 120 min | `.env.example`, `docker-compose.prod.yml` |
| Limitador de intentos del login | **Sí:** 5 intentos por login e IP en 60 s | `LoginRequest::MAX_INTENTOS` y `VENTANA_SEGUNDOS`; ADR-006 |
| Limitadores del asistente | **Sí** | `app/Asistente/LimiteSolicitudes.php`; ADR-006 |
| Caché de aplicación | **No se usa.** `CACHE_STORE=redis` está configurado, pero `app/` no tiene llamadas a `Cache::` ni a `remember()` | ADR-006, «Estado real: Modificada» |
| Caché de permisos y de estructura académica | **No existe.** Cada solicitud lee permisos y estructura de MySQL | ADR-006 (decisión original: guardarlos en Redis; modificada); ADR-003 anota que cada solicitud consulta permisos |
| Colas | **No** (`QUEUE_CONNECTION=sync`); la boleta PDF se genera en la solicitud | ADR-007; Principio VII |

La constitución (Principio VII, «Simplicidad») acepta Redis para sesiones, caché y limitador como la necesidad demostrada por el requisito de 1000 usuarios, y no autoriza colas ni cachés distribuidas adicionales sin necesidad demostrada. La ausencia de caché de permisos fue una decisión de simplicidad, no un olvido; su costo se mide abajo.

## 3. Lo que mostró la sesión de carga del 13/11

Fuente: `specs/006-evaluacion-boletas/evidencias/k6-sesion-2026-11-13.md` (staging B2als_v2, 2 vCPU, `evaluacion.js` con 1020 VUs).

- La CPU del servidor llegó al 100 % hacia los 5-6 min (300-600 VUs, unas 25 solicitudes/s) y siguió saturada.
- Contenedor `app`: p95 152,7 %, máximo 192,7 %. Contenedor `db`: p95 **58,6 %**, máximo **67,6 %** (`docker stats`, en la misma unidad que `app`). La base trabajó mucho, pero el cuello de botella fue `app`.
- Memoria sobrada (≈ 1,1 GB usados; `db` 315 MB). 0 % de solicitudes fallidas.
- Las optimizaciones de código de v0.7.0 (`alumnosPropios` por índices, `hijos()` memoizada, institución y escala una vez por solicitud) **reducen consultas por solicitud, pero su efecto no se midió**: no habrá otra sesión de carga (constitución 2.2.0).
- Límite práctico estimado: ≈ 500 usuarios simultáneos (estimado, no medido). Detalle en [`04`](../01-analisis-de-sistema/04-atributos-de-calidad.md), AC03.

## 4. Qué no se hizo

- Sin caché de aplicación, de permisos ni de estructura académica.
- Sin réplicas de lectura ni particiones. MySQL solo se ajusta por perfil de tamaño en `docker-compose.prod.yml` (`MYSQL_BUFFER_POOL`, 128 MB por defecto, y `MYSQL_MAX_CONNECTIONS`, 40 por defecto); no hay una medición del efecto de esos valores.
- Sin análisis de planes de ejecución (`EXPLAIN`) guardado en el repositorio: el índice de `alumnosPropios` se comprueba por la existencia de la columna indexada, no por el plan.
