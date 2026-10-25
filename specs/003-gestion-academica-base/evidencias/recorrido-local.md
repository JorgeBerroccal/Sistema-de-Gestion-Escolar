# Evidencia: recorrido local del quickstart §2 (SC-001)

**Fecha**: 2026-10-24 · **Rama**: `003-gestion-academica-base`

## 1. Recorrido automatizado (hecho)

`tests/Feature/Academico/RecorridoQuickstartTest.php` ejecuta con los componentes Livewire reales y los roles del
seeder la configuración de una institución nueva: nivel, ficha con escala, año con 4 bimestres (rechazo del
periodo superpuesto y alta corregida), año vigente único, grados, secciones A–C, cursos (rechazo del grado de
otro nivel), asignación de tres cursos en una operación (rechazo todo-o-nada con un curso inactivo) y el
bloqueo de eliminar un curso con asignaciones.

- Resultado: **verde** (34 aserciones).
- Tiempo de máquina de todo el recorrido: **2,66 s**. Mide solo el costo del sistema; no sustituye el
  cronometraje de una persona.

## 2. Recorrido humano cronometrado (pendiente de la persona usuaria)

SC-001 exige configurar una institución nueva (ficha, escala, año con periodos, grados, 3 secciones por grado,
cursos y asignaciones de una sección) en **menos de 30 minutos**. Ese tiempo depende de quien teclea y no puede
medirlo el asistente.

Para registrarlo: ejecutar `docker compose exec app php artisan migrate:fresh --seed` (sin
`AcademicoDemoSeeder`, o vaciando antes los datos de ejemplo), seguir [`quickstart.md`](../quickstart.md) §2 con
un cronómetro y anotar aquí:

| Paso | Minutos |
|---|---|
| Niveles y ficha de la institución | |
| Año escolar con periodos y marcar vigente | |
| Grados | |
| Secciones (3 por grado) | |
| Cursos | |
| Asignaciones de una sección | |
| **Total** | |

Observaciones de usabilidad: _(completar)_
