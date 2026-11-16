# 00 - Necesidad del negocio

> **Pregunta guía:** ¿Qué problema queremos resolver?

Este documento conserva el análisis de la necesidad y agrega la columna **Estado real**: qué se construyó para atenderla. Estados usados en todo `01-analisis-de-sistema`: **Cumplido**, **Parcial**, **Modificado** (se construyó distinto de lo previsto) y **No implementado**. Cada estado cita la spec o la evidencia que lo respalda; la única fuente es este repositorio.

## Problema

Los colegios de primaria y secundaria del Perú, públicos y privados, comparten los mismos procesos académicos: matrícula, asistencia y evaluación. Cuando esos procesos se llevan en papel o en hojas de cálculo separadas, aparecen estos problemas:

| Problema | A quién afecta | Consecuencia | Estado real |
|----------|----------------|--------------|-------------|
| La información académica está dispersa en registros físicos y archivos sueltos. | Director, Administrador | No hay una vista consolidada de matrícula, asistencia y rendimiento. | **Parcial.** Los datos están en una sola base MySQL, pero las consultas del Director son por sección (nóminas, reporte de asistencia, reporte de notas); no hay un consolidado de toda la institución (RF21). |
| El registro de notas y el cálculo de promedios se hacen a mano. | Docente | Toma tiempo y se cometen errores, sobre todo al cierre de bimestre. | **Cumplido.** El Docente guarda la tabla de la sección de una vez y el promedio se calcula al leer (`app/Evaluacion/CalculoPromedios.php`; spec 006, R-04). |
| Las boletas se elaboran y entregan de forma manual. | Docente, Padre de Familia | Las familias reciben la información tarde. | **Cumplido.** Boleta PDF descargable por periodo y anual, con un solo formato (spec 006, R-13). |
| Padres y alumnos deben ir al colegio o esperar al docente para saber notas y faltas. | Padre de Familia, Alumno | Poco seguimiento del avance del estudiante. | **Cumplido.** Pantallas propias de notas, asistencia y boleta, y un asistente virtual (specs 005, 006 y 007). |
| No queda registro de quién modificó una nota. | Director | No se pueden aclarar reclamos ni detectar cambios indebidos. | **Parcial.** Cada nota guarda `registrado_por` (último que la escribió) y cada cierre de periodo guarda `cerrado_por`. No hay bitácora ni valor anterior (ADR-010 no implementada). |
| Los sistemas existentes suelen estar hechos para un colegio en particular. | Institución | Adaptarlos a otra estructura de grados, secciones o escala exige cambiar el código. | **Cumplido.** La estructura académica y la escala son datos (specs 003 y 006); pruebas `GenericidadTest` en los módulos 2 a 5. |

## Visión

*"Proveer un sistema de gestión escolar genérico, ligero y adaptable, que permita a instituciones educativas públicas y privadas de primaria y secundaria digitalizar sus procesos académicos esenciales (matrícula, asistencia y evaluación) con una arquitectura simple de desplegar, correctamente probada y con un asistente virtual basado en IA que facilite el acceso a la información."*

## Objetivos del negocio

| ID | Objetivo del negocio | Cómo lo atiende el SGE | Requisitos relacionados | Estado real |
|----|----------------------|------------------------|-------------------------|-------------|
| ON01 | Centralizar la información académica del colegio en un solo sistema. | Módulos de Gestión Académica, Matrícula, Asistencia y Evaluación sobre una misma base de datos. | RF05–RF18, RF21 | **Parcial.** Los seis módulos comparten una base MySQL; falta el reporte consolidado (RF21). |
| ON02 | Reducir el tiempo y los errores en el registro de notas y la emisión de boletas. | Cálculo automático de promedios y boleta en PDF. | RF15–RF17 | **Cumplido.** Specs 006 (FR-004, FR-007, FR-011). |
| ON03 | Dar a padres y alumnos acceso oportuno a notas y asistencia. | Consulta web desde el celular y asistente virtual con IA. | RF17, RF18, RF19 | **Cumplido** en funciones (specs 005, 006, 007). La usabilidad en celular no se midió (AC08). |
| ON04 | Proteger los datos personales de los estudiantes, que son menores de edad. | Control de acceso por roles y permisos, y auditoría de acciones críticas. | RF01–RF04, RF20, RF22 | **Parcial.** Permisos por tabla, pertenencia fail-closed (ADR-011), contexto mínimo hacia la IA y datos ficticios en staging. La auditoría es solo `registrado_por` y `cerrado_por`. |
| ON05 | Servir a cualquier colegio sin modificar el código. | Estructura académica y escala de calificación configurables. | RF05–RF08 | **Cumplido** con una excepción: la ficha de institución no tiene logo (RF05). |
| ON06 | Operar con un costo que un colegio pequeño pueda asumir. | Despliegue con contenedores en un servidor de bajo costo. | — (RC08, RC09) | **Cumplido** en staging: una VM de Azure for Students de 9,64 USD/mes en uso diario y estimación de 45,45 USD (45,5 % del crédito de 100 USD) hasta fin de año (`docs/despliegue/costo-y-tamano.md`). |

## Alcance que responde a la necesidad

El proyecto se limita a un **Producto Mínimo Viable de 6 módulos** (plazo de dos meses, RC10), fijados por la constitución (Principio IV): Autenticación y Roles, Gestión Académica, Matrícula, Asistencia, Evaluación y Boletas, y Asistente Virtual con IA.

Quedan fuera de esta versión la gestión financiera, las inscripciones en línea, la mensajería interna y los certificados oficiales (RC17).

Previstos en el análisis y **no implementados**: bitácora de auditoría (RF22), reportes consolidados de la institución (RF21), logo de la institución (RF05) y consulta de horarios en el asistente (RF19; el sistema no modela horarios).

## Resultado de esta etapa

Los objetivos ON01–ON06 son la base para los actores ([01](01-actores.md)), las historias de usuario ([02](02-historias-del-usuario.md)), los requisitos funcionales ([03](03-requisitos-funcionales.md)) y los atributos de calidad ([04](04-atributos-de-calidad.md)).
