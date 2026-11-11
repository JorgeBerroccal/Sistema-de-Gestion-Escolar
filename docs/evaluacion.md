# Evaluación y Boletas (Módulo 5)

Referencia funcional del módulo. Diseño y decisiones: [`specs/006-evaluacion-boletas/`](../specs/006-evaluacion-boletas/).

## Flujo

1. **Criterios**: por curso, sección y periodo, el Docente (o el Administrador) define criterios con nombre y peso. Los pesos
   deben ser mayores que 0 y **sumar exactamente 100,00**. Los nombres, cursos y periodos son datos: no hay catálogo fijo.
2. **Notas**: la tabla alumno × criterio muestra solo a los alumnos con matrícula activa y se guarda **de una vez, todo o
   nada** (un POST clásico con CSRF). Una celda vacía significa «sin nota»: no es cero y se excluye del promedio. Si una celda es
   inválida no se guarda nada y el error indica fila y columna (sin nombres de alumnos).
3. **Promedios** (no se guardan; se calculan al leer): ponderado de los criterios con nota, con los pesos renormalizados sobre los
   criterios que sí tienen nota; el promedio anual es el promedio simple de los promedios de periodo que existen. Sin notas
   se muestra «sin notas», nunca 0.
4. **Cierre y publicación**: el Administrador cierra un periodo **por sección**. Cerrado, el Docente ya no puede modificar sus
   notas (el Administrador sí) y las notas y la boleta se publican a Alumnos y Padres. Reabrir lo revierte.
5. **Boleta**: PDF de un solo formato, por periodo o anual, con los datos de la institución, el alumno, la sección, las notas por
   criterio, el promedio por curso y el resumen de asistencia del Módulo 4. Un periodo sin cerrar sale marcado **BORRADOR**.

## Quién ve qué (mínimo privilegio)

| Rol por defecto | Notas | Boleta |
|---|---|---|
| Administrador | Registra y corrige en cualquier sección, también con el periodo cerrado; cierra y reabre | Cualquier alumno, también en borrador |
| Director | Consulta las de todas las secciones | Cualquier alumno, también en borrador |
| Docente | Registra solo los cursos y secciones donde tiene asignación y mientras el periodo esté abierto | **No accede** a la boleta completa |
| Padre de Familia / Alumno | Solo lo propio o de sus hijos, de periodos publicados | Solo la propia o la de sus hijos, de periodos publicados |

Los permisos (`notas.*`, `boletas.*`) viven en la tabla de roles y permisos: se pueden reasignar sin cambiar código.

## La escala de calificación es configuración de la institución

La institución elige su escala en la ficha de la institución. Cada escala (tabla `escalas_calificacion`) define:

- `valores`: lo que se puede escribir (letras, o un rango numérico `minimo`–`maximo`);
- `equivalentes`: solo en escalas literales, el número de cada letra para poder promediar;
- `umbral_aprobacion`: el valor desde el que se aprueba (una letra o un número).

Los valores iniciales de la escala literal (**AD = 4, A = 3, B = 2, C = 1**, umbral **B**) y el umbral **11** de la escala
vigesimal son **una opción razonable para poder promediar, no una regla del Currículo Nacional**: la institución puede
cambiarlos en datos (o crear otra escala con otros códigos) sin tocar el código. Los seeders y las migraciones solo completan
esos valores si están vacíos; nunca pisan lo que el Administrador cambió.

Con escala literal, el promedio se calcula con los equivalentes y se **muestra como la letra más cercana** (en un empate, la de
mayor equivalente); la boleta muestra siempre el calificativo literal, no el número.

## Casos límite

- Un alumno matriculado a mitad de año aparece solo desde su matrícula; sus notas anteriores no existen y no cuentan como cero.
- Un alumno retirado conserva sus notas y boletas y deja de aparecer en las tablas nuevas.
- Un alumno trasladado de sección a mitad de año: sus notas quedan en la sección original y la boleta muestra la sección vigente.
- Cambiar el peso de un criterio recalcula los promedios; eliminar un criterio pide confirmación y **borra sus notas**.
- Solo se edita el año escolar vigente; los años anteriores son de consulta.

## Datos de ejemplo y carga

- `EvaluacionDemoSeeder`: criterios y notas **ficticios** de los alumnos de ejemplo (`9000xxxx`), con el primer periodo cerrado
  en la primera sección. Rechaza producción y no crea cuentas.
- `EvaluacionCargaSeeder`: datos de la sesión única de carga k6 (ver [`despliegue/prueba-de-carga.md`](despliegue/prueba-de-carga.md)).
  Crea cuentas con contraseña conocida: solo local, staging y testing, nunca producción, y las cuentas `carga*` se desactivan al
  terminar la sesión.
