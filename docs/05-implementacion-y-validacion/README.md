# Implementación y validación (Sprint 2)

Índice de **dónde está la evidencia** de cada actividad y entregable del Sprint 2. Aquí no se repite lo ya documentado: se enlaza. Los estados reales (cumplido, parcial, no cumplido) están en [`04`](../01-analisis-de-sistema/04-atributos-de-calidad.md) y [`08`](../01-analisis-de-sistema/08-escenarios-atributos-calidad.md) y no se reinterpretan aquí.

## Actividades y entregables

| Actividad o entregable | Dónde está la evidencia |
|---|---|
| Implementar el **estilo** arquitectónico | Monolito modular: [`docs/02-arquitectura-software/estilo-arquitectonico.md`](../02-arquitectura-software/estilo-arquitectonico.md); ADR-001 en [`07`](../01-analisis-de-sistema/07-decisiones-arquitectonicas.md); código por dominio en `app/` (`Academico`, `Matricula`, `Asistencia`, `Evaluacion`, `Asistente`, `Autorizacion`) |
| Implementar el **patrón** arquitectónico | [`patron-arquitectonico.md`](../02-arquitectura-software/patron-arquitectonico.md) y [`componentes-arquitectonicos.md`](../02-arquitectura-software/componentes-arquitectonicos.md); modelo C4 en [`docs/04-modelo-c4/`](../04-modelo-c4/) |
| **Especificaciones SDD** con criterios de aceptación y tareas | Una carpeta por módulo en [`specs/`](../../specs/): `001-auth-roles-rbac`, `002-despliegue-ci-staging`, `003-gestion-academica-base`, `004-matricula`, `005-asistencia`, `006-evaluacion-boletas`, `007-asistente-virtual`. Cada una tiene `spec.md` (historias con *Acceptance Scenarios* y criterios de éxito SC-nnn), `plan.md`, `research.md`, `data-model.md`, `contracts/`, `quickstart.md`, `tasks.md` y `evidencias/`. Metodología en el [README principal](../../README.md#metodología-spec-driven-development-con-spec-kit) |
| **Endpoints documentados** (OpenAPI/Swagger) | [`openapi.yaml`](openapi.yaml) y [`api-http.md`](api-http.md). **Adaptado**: ver la tabla de adaptaciones |
| **Base de datos y estrategia de caché** | [`base-de-datos-y-cache.md`](base-de-datos-y-cache.md); ADR-006 (Redis, modificada) en [`07`](../01-analisis-de-sistema/07-decisiones-arquitectonicas.md) |
| **SOLID y patrones** | [`principios-de-diseno.md`](../03-diseno-de-software/diseno-interno/principios-de-diseno.md) (SOLID con archivos), [`patrones-de-diseno.md`](../03-diseno-de-software/diseno-interno/patrones-de-diseno.md) y [`diseno-interno-de-modulos.md`](../03-diseno-de-software/diseno-interno/diseno-interno-de-modulos.md) |
| **Pruebas automatizadas** en CI/CD con **GitHub Actions** | Suite de Pest en `tests/Unit` y `tests/Feature`; workflow [`.github/workflows/ci.yml`](../../.github/workflows/ci.yml) (push y pull request: `pruebas`, `estilo`, `scripts`); [`docs/despliegue/integracion-continua.md`](../despliegue/integracion-continua.md); evidencia en [`specs/002-despliegue-ci-staging/evidencias/ci.md`](../../specs/002-despliegue-ci-staging/evidencias/ci.md). **Adaptado** (Jest → Pest) |
| **Reporte de cobertura** | [`cobertura/resumen.md`](cobertura/resumen.md): tabla por carpeta (97,5 % en servicios), artefacto `cobertura` del CI y comando para regenerarlo |
| **Pruebas de los escenarios de atributos de calidad** | Escenarios EQ-01 a EQ-10 con su estado real en [`08`](../01-analisis-de-sistema/08-escenarios-atributos-calidad.md); mediciones M-01 a M-03 en [`docs/retoques.md`](../retoques.md); rendimiento con k6, abajo |
| Pruebas **End to End** (navegador real) | [`pruebas-end-to-end.md`](pruebas-end-to-end.md): cinco flujos con Pest Browser y Playwright en [`tests/Browser/`](../../tests/Browser/), job `e2e` del CI |
| Pruebas de carga con **k6** | Scripts [`tests/Load/carga.js`](../../tests/Load/carga.js) y [`tests/Load/evaluacion.js`](../../tests/Load/evaluacion.js); guía [`docs/despliegue/prueba-de-carga.md`](../despliegue/prueba-de-carga.md); sesión del 13/11: [`specs/006-evaluacion-boletas/evidencias/k6-sesion-2026-11-13.md`](../../specs/006-evaluacion-boletas/evidencias/k6-sesion-2026-11-13.md); sesión de 2000 usuarios del 25/11: [`k6-sesion-2026-11-25.md`](../../specs/006-evaluacion-boletas/evidencias/k6-sesion-2026-11-25.md); sesiones de octubre en [`specs/001-auth-roles-rbac/evidencias/`](../../specs/001-auth-roles-rbac/evidencias/). **Adaptado** (1000 usuarios) |
| Respeto de las **decisiones arquitectónicas** | ADR-001 a ADR-013 con su estado real en [`07`](../01-analisis-de-sistema/07-decisiones-arquitectonicas.md): *Implementada*, *Modificada* o *No implementada*, cada una con cómo se verificó |

## Adaptaciones

Tres entregables se **adaptaron** porque la letra del pedido contradice la arquitectura documentada o los recursos disponibles. Se dice «adaptado», no «cumplido».

| Pedido | Qué se hizo | Estado | Justificación |
|---|---|---|---|
| Pruebas con **Jest** | Pruebas con **Pest** (`tests/Unit`, `tests/Feature`), ejecutadas por GitHub Actions en cada push y pull request, con cobertura | **Adaptado** | El sistema es PHP/Laravel; Jest es de JavaScript y no probaría el código del SGE. Pest cumple el mismo papel: pruebas automáticas en CI con reporte de cobertura (artefacto `cobertura`) |
| **API REST** documentada con OpenAPI/Swagger | [`openapi.yaml`](openapi.yaml) solo con los endpoints HTTP reales que no son Livewire: login, logout, `/up`, guardado de notas, descarga de boletas y cambio de contraseña | **Adaptado** | No hay API REST: la interfaz es Blade + Livewire en el monolito (RC04, ADR-001). Crear una API solo para el entregable contradiría la arquitectura documentada y sería funcionalidad inventada |
| k6 con **hasta 2000** usuarios | k6 con **1000** usuarios virtuales | **Adaptado (pendiente de confirmar)** | El umbral de 1000 usuarios se acordó **verbalmente** con la docente al inicio del proyecto; **no hay un documento escrito**, y el enunciado del Sprint 2 menciona 2000. El responsable lo confirmará con la docente; según su respuesta se actualizará esta fila. La sesión con 2000 usuarios se ejecutó el 25/11 (4 vCPU; enmienda 2.4.0): [`k6-sesion-2026-11-25.md`](../../specs/006-evaluacion-boletas/evidencias/k6-sesion-2026-11-25.md), el p95 tampoco se cumple. Con 1000 la CPU de los 2 vCPU ya se satura y el p95 no se cumple: [`k6-sesion-2026-11-13.md`](../../specs/006-evaluacion-boletas/evidencias/k6-sesion-2026-11-13.md), AC03 en [`04`](../01-analisis-de-sistema/04-atributos-de-calidad.md) |

## Despliegue final (v1.0.0)

`v1.0.0` es la versión final del MVP. Se etiquetó sobre `main` el 23/11/2026, el workflow `release` publicó las imágenes y se desplegó en staging (https://sge-peru.chilecentral.cloudapp.azure.com) en 36 s, con `/up` informando `version=v1.0.0` y 32 permisos. El registro completo del despliegue y de la comprobación en el navegador está en [`docs/retoques.md`](../retoques.md#verificación-en-staging-v100-2311). Entre `v0.7.1` y `v1.0.0` no cambió el código de la aplicación, solo la documentación y el CI; la prueba de humo de 8 pasos es la de `v0.7.1`.

## Criterio fundamental: ¿responde a los atributos de calidad?

Resumen de los estados reales (fuente: [`08`](../01-analisis-de-sistema/08-escenarios-atributos-calidad.md), tabla resumen). Hay atributos **no cumplidos**, y se muestran así:

- **Cumplidos:** seguridad (EQ-01), privacidad en IA (EQ-02), adaptabilidad (EQ-06, tiempo no medido) y testeabilidad (EQ-07).
- **Parciales:** disponibilidad (EQ-04), mantenibilidad (EQ-05), usabilidad (EQ-08) y portabilidad (EQ-09).
- **No cumplidos:** rendimiento con 1000 usuarios en p95 (EQ-03; 0 % de solicitudes fallidas) y trazabilidad (EQ-10).

## Qué no se hizo

- Una tercera sesión de carga ni ajustes para cumplir los umbrales de p95: la sesión de 2000 usuarios (25/11, enmienda 2.4.0) se ejecutó y no los cumplió; la fila de k6 de las adaptaciones sigue pendiente de confirmar con la docente.
- Prueba de usabilidad con 5 usuarios (AC08).
- Una API REST, caché de aplicación, cola de trabajos y bitácora de auditoría (ADR-006, ADR-007 y ADR-010 en `07`).
