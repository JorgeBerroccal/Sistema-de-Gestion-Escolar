# Modelo de datos: Asistente Virtual

**No hay tablas ni migraciones nuevas.** El módulo lee datos de los Módulos 2–5 y no guarda conversaciones.

## Datos que se agregan

| Dato | Dónde | Detalle |
|---|---|---|
| Permiso `asistente.usar` | `permisos` + `rol_permiso` (seeder) | Descripción: «Usar el asistente virtual». Asignado a Padre de Familia y Alumno. |
| Contadores del limitador | Redis (clave por usuario y global) | Efímeros; los gestiona `RateLimiter`. |
| Configuración | `config/asistente.php` + `.env` | Ver abajo. |

## Configuración (`config/asistente.php`)

| Clave | Variable de entorno | Por defecto |
|---|---|---|
| `proveedor` | `ASISTENTE_PROVEEDOR` | `falso` (`falso` \| `openrouter`) |
| `openrouter.clave` | `OPENROUTER_API_KEY` | vacío (no versionar) |
| `openrouter.url` | `ASISTENTE_OPENROUTER_URL` | `https://openrouter.ai/api/v1` |
| `openrouter.modelo` | `ASISTENTE_OPENROUTER_MODELO` | vacío (obligatorio si se usa) |
| `openrouter.respaldo` | `ASISTENTE_OPENROUTER_RESPALDO` | lista vacía (CSV) |
| `openrouter.datos_reales` | `ASISTENTE_OPENROUTER_DATOS_REALES` | `false` |
| `openrouter.timeout` | `ASISTENTE_TIMEOUT` | 20 (segundos) |
| `limites.usuario_minuto` / `usuario_dia` | `ASISTENTE_LIMITE_USUARIO_MINUTO` / `_DIA` | 3 / 15 |
| `limites.global_minuto` / `global_dia` | `ASISTENTE_LIMITE_GLOBAL_MINUTO` / `_DIA` | 18 / 45 |
| `mensaje.maximo` | `ASISTENTE_MENSAJE_MAXIMO` | 500 (caracteres) |
| `conversacion.turnos` | `ASISTENTE_TURNOS` | 6 |

## Estado en memoria (no persistente)

- **Turno**: `{rol: usuario|asistente, texto}`; lista de los últimos N turnos en el componente Livewire.
- **ContextoAsistente**: texto construido por pregunta; no se guarda ni se registra.

## Datos leídos (fuente única)

| Necesidad | Fuente reutilizada |
|---|---|
| Alumnos propios o hijos | `MatriculaRegla::alumnosPropios($usuario)` |
| Periodos publicados | `ConsultaNotas::periodosPublicados($alumno)` (cierre = publicado) |
| Notas y promedio por periodo | `ConsultaNotas::delAlumno($alumno, $periodo, $usuario)` |
| Asistencia | `ResumenAsistencia::delAlumno($alumno, $desde, $hasta)` |
| Matrícula y sección | `Matricula` activa del alumno, con `with(['seccion.grado.nivel','anio'])` |

## Campos que nunca entran al contexto

`numero_documento`, `tipo_documento_id`, `fecha_nacimiento`, correo de cualquier usuario, `usuario_id`,
datos de apoderados, ids internos.
