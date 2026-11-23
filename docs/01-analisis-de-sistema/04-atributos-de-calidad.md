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
| AC05 | **Mantenibilidad** | El sistema debe estar organizado en módulos independientes (monolito modular), de manera que se pueda modificar o agregar un módulo sin afectar a los demás. | **Parcial.** El código está organizado por dominio en `app/` (`Academico`, `Matricula`, `Asistencia`, `Evaluacion`, `Asistente`, `Autorizacion`). Medición M-03 (20/11) sobre el retoque R-01: 0 archivos de código de otro módulo y 0 puntos de registro compartidos (2 archivos de `app/Support` sin clasificar por la regla). **Es una sola muestra** y no hay pruebas `arch()` de Pest; las reglas se verifican con pruebas específicas (`SinCondicionalesDeRolTest`, `GenericidadTest`). Evidencia: [`docs/retoques.md`](../retoques.md), M-03. |
| AC06 | **Adaptabilidad / Configurabilidad** | Una nueva institución debe poder configurar sus niveles, grados, secciones, cursos, periodos y escala de calificación sin cambios en el código fuente. | **Cumplido** en el diseño: todo es dato (constitución, Principio II) y cada módulo tiene una `GenericidadTest`. **La configuración en una hora no se cronometró** (constitución 2.2.0). Excepción: sin logo. |
| AC07 | **Testeabilidad** | La lógica de negocio (cálculo de promedios, validación de matrícula, reglas RBAC) debe poder probarse de forma aislada con pruebas unitarias y de integración automatizadas antes de cada despliegue. | **Cumplido.** Suite Pest en `tests/Unit` y `tests/Feature`; el CI ejecuta `pest --ci` y `pint --test` en cada push y pull request. Medición M-02 (20/11, local, PCOV): **951 pruebas en verde y 97,5 % de cobertura de líneas en la capa de servicios** (meta ≥ 80 %). Limitaciones: la cobertura se midió en local y **el CI sigue con `coverage: none`**; es cobertura de líneas, no de la calidad de las aserciones. Las pruebas usan MySQL (`sge_testing`), no SQLite. Evidencia: [`docs/retoques.md`](../retoques.md), M-02. |
| AC08 | **Usabilidad** | La interfaz debe ser responsive y fácil de usar desde celulares y tablets. | **Parcial.** Interfaz con Blade, Livewire y Tailwind. Medición M-01 (20/11, Playwright con Chromium a 360 px, no un celular real): **sin desplazamiento horizontal** en las siete pantallas revisadas; la boleta se descarga en **2 interacciones** por las tarjetas del Inicio y en **4** por el menú lateral (meta: 3). **No se hizo la prueba con 5 usuarios.** Evidencia: [`docs/retoques.md`](../retoques.md), M-01. |
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
| AC05 | Mantenibilidad | 0 archivos modificados fuera del módulo; 0 consultas desde controladores y vistas | `git diff --stat` sobre R-01 (`7d4b53c..a1e0114`), con tres grupos de archivos; no hay `arch()` | 0 archivos de otro módulo en una muestra; consultas desde controladores y vistas no medidas |
| AC06 | Adaptabilidad | 0 cambios de código para dos colegios; configuración en 1 h o menos | `GenericidadTest` por módulo | Sin cambios de código: cumplido; tiempo no medido |
| AC07 | Testeabilidad | 100 % de reglas críticas con prueba; cobertura de servicios ≥ 80 % | Suite Pest con PCOV en local (20/11) | 97,5 % de cobertura de líneas en servicios; 951 pruebas en verde; el CI no mide cobertura |
| AC08 | Usabilidad | 90 % de tareas en 5 usuarios; 3 interacciones hasta la boleta; sin scroll horizontal a 360 px | Playwright a 360 px con `padre.demo` (20/11); la prueba con 5 usuarios no se hizo | Sin scroll horizontal; 2 interacciones por las tarjetas y 4 por el menú; prueba con usuarios no realizada |
| AC09 | Portabilidad | Despliegue en un VPS limpio en 30 min o menos | Despliegue de versión cronometrado; servidor nuevo no cronometrado | Escenario cumplido; la medida de 30 min desde cero no se cronometró (27 s por versión). Ver «Relación con los escenarios» |
| AC10 | Trazabilidad | 100 % de acciones críticas con usuario, fecha, valor anterior y nuevo | No hay bitácora | Medida no cumplida; el escenario queda parcial. Ver «Relación con los escenarios» |

## Ejemplo funcional vs. calidad

- **Funcional:** el sistema debe permitir registrar notas.
- **Calidad:** el registro de notas debe ser seguro (solo el docente asignado) y responder rápidamente incluso al cierre del bimestre. En lo construido, lo primero se cumple (`EvaluacionService::puedeEscribir`); lo segundo no con 1000 usuarios en 2 vCPU (AC03); la parte de auditoría es parcial (AC10).

## Escenarios medibles

Cada atributo se convierte en un escenario con fuente, estímulo, artefacto, entorno, respuesta y medida en [`08-escenarios-atributos-calidad.md`](08-escenarios-atributos-calidad.md) (EQ-01 a EQ-10).

## Relación con los escenarios

Los estados de esta tabla juzgan el **atributo** según lo construido; los de [`08`](08-escenarios-atributos-calidad.md) juzgan la **medida** del escenario. Coinciden salvo en dos casos:

| Atributo | Escenario | Por qué difieren |
|----------|-----------|------------------|
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
