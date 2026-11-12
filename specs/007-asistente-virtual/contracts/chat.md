# Contrato: pantalla del chat

| Elemento | Detalle |
|---|---|
| Ruta | `GET /asistente` (`asistente.index`), dentro del grupo `auth` + `activo` |
| Autorización | permiso `asistente.usar` comprobado en `mount()` (403 si falta); ítem de menú «Asistente virtual» con ese permiso |
| Acción | `enviar()` (Livewire): valida mensaje (obligatorio, ≤ máximo, texto), llama a `AsistenteService::preguntar($usuario, $mensaje, $turnos)` |
| Estado | `turnos` (últimos N, solo memoria); recargar reinicia el chat |
| Aviso | Banner fijo en toda vista del chat: «Las respuestas las genera una IA y pueden contener errores. Para datos oficiales consulta Mis notas, Mi asistencia o tu boleta.» |

## Resultados de `preguntar()`

| Resultado | Cuándo | Llama al proveedor |
|---|---|---|
| `respuesta(texto)` | todo en orden | sí (1 solicitud) |
| `aclaracion(texto)` | varios hijos y no se identifica uno | no |
| `sinDatos(texto)` | sin alumnos vinculados o sin matrícula | no |
| `noDisponible(texto)` | producción con adaptador sin datos reales | no |
| `limite(texto, segundos)` | superó el límite por usuario o el global | no |
| `error(texto)` | proveedor caído, sin cuota o respuesta inválida | sí (intento) |

Todos los textos están en español y sin datos técnicos. El orden de evaluación es: validación → disponibilidad
→ límite → alumno → contexto → proveedor.
