# Contrato: Rutas web

**Feature**: `006-evaluacion-boletas`

HTML (Blade/Livewire) bajo el grupo `auth` + `activo`. Sin permiso → **403**; recurso ajeno → **403** sin revelar datos.

| Método | URI | Nombre | Destino | Permiso |
|---|---|---|---|---|
| GET | `/notas/registro` | `notas.registro.index` | `Registro\Index` | `notas.gestionar` **o** `notas.registrar_propia` |
| GET | `/notas/registro/{seccion}/{curso}` | `notas.registro.show` | `Registro\Show` | ídem + par (sección, curso) tomable |
| POST | `/notas/registro/{seccion}/{curso}` | `notas.registro.guardar` | `NotasController@guardar` | ídem + par tomable, año vigente y (Docente) periodo abierto |
| GET | `/notas/cierres` | `notas.cierres.index` | `Cierres\Index` | `notas.cerrar_periodo` |
| GET | `/notas/mias` | `notas.propia.index` | `Propia\Index` | `notas.ver_propia` |
| GET | `/notas/reportes` | `notas.reportes.index` | `Reportes\Index` | `notas.ver_todas` **o** `notas.registrar_propia` |
| GET | `/boletas/{alumno}/{periodo}` | `boletas.descargar` | `BoletaController@descargar` | ver abajo |

- `Registro\Show` recibe el periodo como `?periodo=` (por defecto el vigente por fecha). Muestra el editor de criterios
  (agregar, quitar con confirmación, pesos con total en vivo) y la tabla alumno × criterio con el promedio del periodo
  por alumno. Dos acciones separadas, cada una todo o nada: *Guardar criterios* (Livewire) y *Guardar notas*, un
  **formulario clásico** `POST` con `@csrf` a `notas.registro.guardar` (R-18). Con periodo cerrado y usuario sin
  `notas.gestionar` la tabla es solo lectura y no ofrece guardar.
- `notas.registro.guardar` recibe `periodo` (id) y `notas[alumno_id][criterio_id]` (vacío = sin nota). Autoriza antes de
  tocar datos: 403 sin permiso o par ajeno; si el periodo está cerrado (Docente), el año no es vigente o hay una celda
  fuera de escala o un alumno/criterio ajeno a la sección, **no se guarda nada** y vuelve con `302` y errores por
  fila/columna (sin nombres). Éxito: `302` con aviso. Sesión vencida o sin CSRF → 419.
- `Cierres\Index` lista secciones del año vigente × periodos con su estado (paginado) y acciones cerrar/reabrir.
- `Propia\Index` lista a los hijos (o al propio alumno), con selector de periodo (solo publicados), detalle por curso y
  criterio, promedio y enlace «Descargar boleta» (periodo y anual).
- `Reportes\Index` elige sección (solo las visibles) y periodo; muestra promedios por curso, orden de mérito paginado
  y desaprobados por curso; sin controles de edición.
- `boletas.descargar`: `{periodo}` es el id de un periodo o `anual`. Autoriza **antes** de generar:
  1. `boletas.gestionar` → cualquier alumno; `boletas.generar_propia` → alumno de una sección con asignación mía;
     `boletas.ver_propia` → alumno propio o hijo y solo periodos con cierre (anual: solo con ≥ 1 periodo publicado,
     y solo incluye los publicados).
  2. Periodo sin cierre → solo Admin/Docente, PDF con marca «BORRADOR».
  Con escala literal el PDF muestra el calificativo literal del promedio. Respuesta: `200 application/pdf` con `Content-Disposition: attachment`; 403 si no autorizado; 404 si el periodo no
  existe. Nombre del archivo sin datos personales (`boleta-{alumno_id}-{periodo|anual}.pdf`).
- Menú (`config/menu.php`): Registro de notas (`gestionar`/`registrar_propia`), Cierres de periodo
  (`cerrar_periodo`), Mis notas (`ver_propia`), Reportes de notas (`ver_todas`/`registrar_propia`).
  Las rutas con permiso doble lo comprueban en `mount()`.
