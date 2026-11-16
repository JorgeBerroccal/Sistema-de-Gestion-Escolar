# Nivel 4 · Diagrama de código

> **Proyecto:** Sistema de Gestión Escolar (SGE) · **Módulo detallado:** Evaluación y Boletas · **Modelo:** C4 · Nivel 4 de 4 · **Estado:** tal como se construyó (v0.7.0)

## 1. Objetivo

Mostrar las clases del módulo Evaluación y cómo colaboran en el flujo **Guardar notas**. Se elige este módulo porque concentra las reglas de escala, promedio y cierre. Estructura de carpetas y reglas de dependencia: [diseño interno de módulos](../03-diseno-de-software/diseno-interno/diseno-interno-de-modulos.md).

## 2. Diagrama de clases

![Clases del módulo Evaluación](../img/nivel4-clases-evaluacion.png)

> Fuente editable: [`img/fuentes/nivel4-clases-evaluacion.archify.json`](../img/fuentes/nivel4-clases-evaluacion.archify.json) · versión interactiva: [`nivel4-clases-evaluacion.html`](../img/fuentes/nivel4-clases-evaluacion.html)

| Clase | Tipo | Parte | Responsabilidad | Código |
|---|---|---|---|---|
| `NotasController` | Controlador HTTP | Controlador | Recibe el POST con CSRF de la tabla, comprueba `puedeEscribir` y llama al servicio | `app/Http/Controllers/NotasController.php` |
| `Registro\Show` | Componente Livewire | Controlador | Pantalla de notas: periodo y editor de criterios (`guardarEsquema`) | `app/Livewire/Evaluacion/Registro/Show.php` |
| `EvaluacionService` | Servicio | Servicio | Permiso y asignación, año vigente, periodo abierto, criterios, transacción y `upsert` de notas | `app/Evaluacion/EvaluacionService.php` |
| `CalculoPromedios` | Clase pura | Servicio | Normaliza el valor con la escala; promedios, etiqueta y aprobación | `app/Evaluacion/CalculoPromedios.php` |
| `AsignacionDocente` | Modelo Eloquent | Modelo | Asignación docente–sección–curso que `puedeEscribir` consulta | `app/Models/AsignacionDocente.php` |
| `CriterioEvaluacion` | Modelo Eloquent | Modelo | Criterios del par (sección, curso, periodo): definen las columnas válidas y el `criterio_id` de cada nota | `app/Models/CriterioEvaluacion.php`, `EvaluacionService::criteriosDelPar` |
| `Nota` | Modelo Eloquent | Modelo | Fila escrita con `upsert` o borrada; guarda `registrado_por` | `app/Models/Nota.php` |
| `EscalaCalificacion` | Modelo Eloquent | Modelo | Escala configurada (literal o numérica) con la que se crea `CalculoPromedios` | `app/Models/EscalaCalificacion.php` |

La excepción de dominio `EvaluacionIncumplida` cancela el guardado completo si un valor no pertenece a la escala; no se dibuja para no saturar el diagrama.

La **pertenencia** (`NotaRegla`) no interviene al guardar: el permiso de escritura lo decide `puedeEscribir`, y la regla solo filtra las lecturas de `ConsultaNotas` (`visiblePara`).

No hay interfaz `Escala` con estrategias ni eventos: la escala es **dato** (`EscalaCalificacion`) y una sola clase la interpreta.

## 3. Diagrama de secuencia: guardar notas

![Secuencia «Guardar notas»](../img/nivel4-secuencia-registrar-nota.png)

> Fuente editable: [`img/fuentes/nivel4-secuencia-registrar-nota.archify.json`](../img/fuentes/nivel4-secuencia-registrar-nota.archify.json) · versión interactiva: [`nivel4-secuencia-registrar-nota.html`](../img/fuentes/nivel4-secuencia-registrar-nota.html)

| Paso | Qué ocurre | Código |
|---|---|---|
| 1–2 | El middleware exige sesión y cuenta activa | `EnsureUserIsActive` |
| 3–4 | El controlador comprueba `puedeEscribir` (403 si no) | `NotasController.php` |
| 5 | Invoca `guardarNotas`, que repite la comprobación en `exigirEscritura` | `EvaluacionService.php` |
| 6–7 | Se consultan año vigente, periodo, criterios y nómina | `EvaluacionService.php` |
| 8–9 | Cada celda se normaliza con la escala; un error cancela todo | `CalculoPromedios::normalizar` |
| 10 | En una transacción: `upsert` de las llenas y borrado de las vacías | `EvaluacionService::guardarNotas` |
| 11–12 | Redirección con el recuento de notas guardadas | `NotasController.php` |

Los promedios no se recalculan al guardar: se calculan al consultar (`ConsultaNotas`).
