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

## 2. Recorrido humano cronometrado (descartado)

**Decisión del responsable del proyecto (2026-11-04)**: no se realiza el cronometraje de SC-001 por falta de
tiempo. La evidencia que lo sustituye es el recorrido automatizado (§1, en verde) y el recorrido manual
del quickstart en staging, que cubrió el flujo completo sin cronómetro
([`staging.md`](staging.md)). SC-001 queda sin medición de tiempo humano.
