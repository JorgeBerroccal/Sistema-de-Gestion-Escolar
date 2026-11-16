# 04 - Atributos de calidad

> Los atributos de calidad indican **cómo** debe funcionar el sistema, más allá de **qué** debe hacer.

La columna **Estado real** compara cada atributo con lo construido y medido. Estados: **Cumplido**, **Parcial**, **No cumplido**, **No medido**. Las cifras citadas salen de la evidencia indicada; ninguna es una estimación nueva.

## Escenario de análisis

Al **cierre de un bimestre**, todos los docentes registran notas de forma simultánea y, apenas se publican, muchos padres de familia y alumnos entran a consultar notas y asistencia, descargar las boletas y preguntar al asistente virtual. El pico se probó con una **sesión de carga de 1000 usuarios virtuales en k6** sobre el servidor de staging, con subida escalonada y pausas entre acciones, y con datos personales de **menores de edad** (en staging solo hay datos ficticios). Los scripts están en `tests/Load/` (`carga.js` para el Módulo 1 y `evaluacion.js` para el Módulo 5); el asistente virtual **no** forma parte de la carga (RC18).

Atributos relevantes: seguridad, rendimiento, disponibilidad, mantenibilidad, adaptabilidad, testeabilidad, usabilidad, portabilidad y trazabilidad.

## Atributos de calidad

| ID | Atributo de calidad | Escenario de calidad | Estado real |
|----|---------------------|----------------------|-------------|
| AC01 | **Seguridad** | Cada usuario solo puede ver y modificar la información que le permite su rol: un padre solo ve a sus hijos y un docente solo registra notas en los cursos y secciones que tiene asignados. Las contraseñas se almacenan cifradas (hash). | **Cumplido.** Permiso por tabla más filtro de pertenencia fail-closed (ADR-011); `password` con cast `hashed` y costo bcrypt 12. Pruebas: `tests/Feature/Autorizacion/` (rutas, gates, menú, sin condicionales de rol), `tests/Feature/*/Acceso` y `*/Pertenencia`. |
| AC02 | **Seguridad (privacidad en IA)** | El asistente virtual nunca debe revelar datos de otros estudiantes: al servicio de IA solo se envía el contexto autorizado para el usuario autenticado. | **Cumplido.** Spec 007, SC-002 a SC-004, R-13. `tests/Feature/Asistente/` (`AccesoAjenoTest`, `MinimizacionTest`, `NoPublicadoTest`, `ConstructorContextoTest`) verifican lo que recibe el adaptador falso. |
| AC03 | **Rendimiento** | Durante el cierre de bimestre, el inicio de sesión, el registro de notas y la descarga de boletas deben responder en tiempos aceptables con **1000 usuarios virtuales simultáneos**. Meta del laboratorio: p95 < 2 s y < 1 % de errores. | **No cumplido en p95.** Con 2 vCPU: Módulo 1, login p95 5,52 s (umbral 3,5 s) y consultas 3,39 s (umbral 1,5 s); Módulo 5, login 15,34 s, consulta 13,89 s, notas 14,43 s y boleta 16,40 s, todos sobre su umbral. **Sí se cumplió** el 0 % de solicitudes fallidas. Cuello de botella: la CPU del contenedor `app`. Límite práctico estimado: ~500 usuarios (inferido, no medido). Evidencia: `specs/006-evaluacion-boletas/evidencias/k6-sesion-2026-11-13.md`; spec 006, R-23. |
| AC04 | **Disponibilidad** | El sistema debe permanecer accesible en los periodos críticos y no debe caerse bajo la carga: las tareas pesadas, como la boleta PDF, no deben bloquear las solicitudes interactivas. Ante un reinicio del servidor, los contenedores deben levantarse automáticamente. | **Parcial.** Los cuatro contenedores tienen `restart: unless-stopped` y tras reiniciar la VM la pila volvió sola (a los 3 min estaban en marcha; `specs/002-despliegue-ci-staging/evidencias/despliegue-y-vuelta-atras.md`). No hubo caídas durante la carga (0 % de fallos). **La boleta PDF no está aislada**: se genera en la solicitud (spec 006, R-13) y en la carga fue la operación más lenta. |
| AC05 | **Mantenibilidad** | El sistema debe estar organizado en módulos independientes (monolito modular), de manera que se pueda modificar o agregar un módulo sin afectar a los demás. | **Parcial.** El código está organizado por dominio en `app/` (`Academico`, `Matricula`, `Asistencia`, `Evaluacion`, `Asistente`, `Autorizacion`). No hay pruebas `arch()` de Pest (ver «Cómo se verificó»); las reglas se verifican con pruebas específicas (`SinCondicionalesDeRolTest`, `GenericidadTest`). |
| AC06 | **Adaptabilidad / Configurabilidad** | Una nueva institución debe poder configurar sus niveles, grados, secciones, cursos, periodos y escala de calificación sin cambios en el código fuente. | **Cumplido** en el diseño: todo es dato (constitución, Principio II) y cada módulo tiene una `GenericidadTest`. **La configuración en una hora no se cronometró** (constitución 2.2.0). Excepción: sin logo. |
| AC07 | **Testeabilidad** | La lógica de negocio (cálculo de promedios, validación de matrícula, reglas RBAC) debe poder probarse de forma aislada con pruebas unitarias y de integración automatizadas antes de cada despliegue. | **Parcial.** Suite Pest en `tests/Unit` y `tests/Feature`: **910 pruebas pasadas (3797 aserciones)** en el CI de `main` (ejecución 43945958777, commit `b672143`, 64,9 s); el CI ejecuta `pest --ci` y `pint --test` en cada push y pull request. **El porcentaje de cobertura no se mide** (el CI usa `coverage: none`). Las pruebas usan MySQL (`sge_testing`), no SQLite. |
| AC08 | **Usabilidad** | La interfaz debe ser responsive y fácil de usar desde celulares y tablets. | **No medido.** Interfaz con Blade, Livewire y Tailwind; no hay prueba con usuarios ni revisión a 360 px registrada. El criterio de 3 clics hasta la boleta (spec 006, SC-002) está en la spec, sin medición cronometrada. |
| AC09 | **Portabilidad / Desplegabilidad** | El sistema debe poder desplegarse en cualquier servidor con Docker, con un entorno equivalente entre desarrollo y producción. | **Cumplido.** `docker compose up` en desarrollo y `docker-compose.prod.yml` con imágenes de GHCR en staging: los mismos cuatro servicios. Despliegue de una versión en 27 s y vuelta atrás en 22 s (`despliegue-y-vuelta-atras.md`). El aprovisionamiento desde cero no se cronometró. |
| AC10 | **Trazabilidad (auditoría)** | Toda acción crítica debe quedar registrada con usuario, fecha y hora, para poder identificar quién modificó una nota o una cuenta. | **Parcial.** `registrado_por` y marcas de tiempo en notas y asistencia; `cerrado_por` y `cerrado_en` en cierres de periodo. No hay bitácora ni valores anteriores (ADR-010 no implementada). |

## ¿Cómo se mide cada atributo?

Cada atributo tiene una métrica con su meta, un instrumento y el resultado real.

| ID | Atributo | Métrica y meta (diseño) | Cómo se midió realmente | Resultado |
|----|----------|-------------------------|-------------------------|-----------|
| AC01 | Seguridad | 100 % de accesos no autorizados rechazados; 100 % de contraseñas con hash | Pruebas Pest de acceso por rol y por ruta; cast `hashed` en `Usuario` | Pruebas en verde en el CI; sin matriz formal rol × ruta única (hay matrices por módulo, p. ej. `Academico/Acceso/MatrizDeAccesoTest.php`) |
| AC02 | Privacidad en IA | 0 datos de otros estudiantes en el contexto enviado a la IA | Pruebas con `ProveedorFalso`, que guarda lo recibido | Cumplido (sobre el contexto, no sobre el texto de la respuesta) |
| AC03 | Rendimiento | p95 < 2 s y errores < 1 % con 1000 usuarios | k6 en staging B2als_v2, rampa a 1000 VUs, con umbrales por endpoint (`specs/006-evaluacion-boletas/contracts/prueba-de-carga.md`) | No cumplido en p95; fallos 0 % |
| AC04 | Disponibilidad | Recuperación en 2 min o menos tras un reinicio; 0 caídas durante la carga | Reinicio desde el portal y revisión de contenedores | Pila arriba a los 3 min (no cronometrado con precisión); 0 caídas |
| AC05 | Mantenibilidad | 0 archivos modificados fuera del módulo; 0 consultas desde controladores y vistas | No se midió con `git diff` ni con `arch()` | No medido |
| AC06 | Adaptabilidad | 0 cambios de código para dos colegios; configuración en 1 h o menos | `GenericidadTest` por módulo | Sin cambios de código: cumplido; tiempo no medido |
| AC07 | Testeabilidad | 100 % de reglas críticas con prueba; cobertura de servicios ≥ 80 % | Suite Pest; cobertura no calculada | Reglas críticas cubiertas por specs y tareas; cobertura no medida |
| AC08 | Usabilidad | 90 % de tareas en 5 usuarios; 3 interacciones hasta la boleta; sin scroll horizontal a 360 px | No se hizo | No medido |
| AC09 | Portabilidad | Despliegue en un VPS limpio en 30 min o menos | Despliegue de versión cronometrado; servidor nuevo no cronometrado | Escenario cumplido; la medida de 30 min desde cero no se cronometró (27 s por versión). Ver «Relación con los escenarios» |
| AC10 | Trazabilidad | 100 % de acciones críticas con usuario, fecha, valor anterior y nuevo | No hay bitácora | Medida no cumplida; el escenario queda parcial. Ver «Relación con los escenarios» |

## Ejemplo funcional vs. calidad

- **Funcional:** el sistema debe permitir registrar notas.
- **Calidad:** el registro de notas debe ser seguro (solo el docente asignado) y responder rápidamente incluso al cierre del bimestre. En lo construido, lo primero se cumple (`EvaluacionService::puedeEscribir`); lo segundo no con 1000 usuarios en 2 vCPU (AC03); la parte de auditoría es parcial (AC10).

## Escenarios medibles

Cada atributo se convierte en un escenario con fuente, estímulo, artefacto, entorno, respuesta y medida en [`08-escenarios-atributos-calidad.md`](08-escenarios-atributos-calidad.md) (EQ-01 a EQ-10).

## Relación con los escenarios

Los estados de esta tabla juzgan el **atributo** según lo construido; los de [`08`](08-escenarios-atributos-calidad.md) juzgan la **medida** del escenario. Coinciden salvo en tres casos:

| Atributo | Escenario | Por qué difieren |
|----------|-----------|------------------|
| AC05 **Parcial** | EQ-05 **No medido** | AC05 se juzga por lo observable en el código: módulos por dominio y pruebas de reglas específicas. EQ-05 exige medir cuántos archivos toca un cambio y pruebas `arch()`, y eso no se hizo. |
| AC09 **Cumplido** | EQ-09 **Parcial** | AC09 pide que el sistema pueda desplegarse con un entorno equivalente: se logra con Docker Compose e imágenes de GHCR. EQ-09 mide el aprovisionamiento completo de un servidor nuevo en 30 minutos, que no se cronometró. |
| AC10 **Parcial** | EQ-10 **No cumplido** | AC10 se cumple en parte: hay usuario y fecha en notas, asistencia y cierres. EQ-10 exige el 100 % de las acciones críticas con valor anterior y nuevo, y no se alcanza. |

## Cómo se verificó

| Punto | Comprobación | Resultado |
|-------|--------------|-----------|
| Caché de permisos (parte de AC03) | Búsqueda de `Cache::`, `cache(` y `remember` en `app/`; lectura de `Usuario::tienePermiso()` | No hay caché de permisos ni de estructura académica. `tienePermiso()` guarda los permisos en una propiedad del objeto durante la solicitud (`identificadoresPermiso`). El plan 001 lo declara: «sin caché de permisos». Redis se usa para sesiones, el limitador de intentos y el limitador del asistente. |
| Pruebas `arch()` (AC05) | Búsqueda de `arch(` y de las expectativas de arquitectura de Pest en `tests/` | Sin coincidencias. |
| Cobertura (AC07) | Lectura de `.github/workflows/ci.yml` | `coverage: none`; el CI corre `pest --ci` y `pint --test`. |
| Reinicio (AC04) | Lectura de `docker-compose.prod.yml` y de la evidencia de despliegue | `restart: unless-stopped` en los cuatro servicios; verificación a los 3 min. |
| Cantidad de pruebas (AC07) | Registro del job `pruebas` de GitHub Actions en la ejecución 43945958777 de `main` (commit `b672143`, fusión de la PR #15, que corrige la prueba `NoPublicadoTest`), vía `gh api …/actions/jobs/<id>/logs` | `Tests: 910 passed (3797 assertions)`, `Duration: 64.89s`. Es el resultado de una ejecución real del CI, no un conteo de archivos. |
