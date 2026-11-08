# Contrato: Rutas web

**Feature**: `005-asistencia`

HTML (Blade/Livewire) bajo el grupo `auth` + `activo`, prefijo `/asistencia`. Sin permiso → **403**.

| Método | URI | Nombre | Componente | Permiso |
|---|---|---|---|---|
| GET | `/asistencia/tomar` | `asistencia.tomar.index` | `Tomar\Index` | `gestionar` **o** `registrar_propia` |
| GET | `/asistencia/tomar/{seccion}` | `asistencia.tomar.show` | `Tomar\Show` | ídem + sección tomable (asignación o `gestionar`) |
| GET | `/asistencia/mia` | `asistencia.propia.index` | `Propia\Index` | `asistencia.ver_propia` |
| GET | `/asistencia/reportes` | `asistencia.reportes.index` | `Reportes\Index` | `ver_todas` **o** `registrar_propia` |
| GET | `/asistencia/reportes/{seccion}/alumnos/{alumno}` | `asistencia.reportes.alumno` | `Reportes\Alumno` | ídem + sección visible |

- `Tomar\Show` recibe la fecha como parámetro de consulta (`?fecha=`; por defecto hoy). Muestra la lista con un
  selector de estado y observación por alumno, guarda todo en una operación y permite justificar/revertir faltas.
  En sábado/domingo, fecha futura o año no vigente muestra el aviso y no ofrece guardar.
- `Propia\Index` lista a los hijos (o al propio alumno), con selector de mes y resumen del año.
- `Reportes\Index` elige sección (solo las visibles), rango o periodo; muestra totales por estado, porcentaje y
  ranking de faltas paginado, sin controles de edición; enlaza al detalle por alumno.
- Un `{seccion}` o `{alumno}` ajeno → 403 sin revelar datos.
- Menú: Tomar asistencia (`gestionar`/`registrar_propia`), Mi asistencia (`ver_propia`), Reportes de asistencia
  (`ver_todas`/`registrar_propia`). Las rutas con permiso doble lo comprueban en `mount()`.
