# 08 - Escenarios de atributos de calidad

> Un escenario de atributo de calidad describe de forma **concreta y medible** cómo debe responder el sistema ante un estímulo. Convierte cada atributo de [`04-atributos-de-calidad.md`](04-atributos-de-calidad.md) en algo que se puede comprobar.

Cada escenario tiene seis partes:

| Parte | Qué responde |
|-------|--------------|
| **Fuente del estímulo** | ¿Quién o qué lo origina? |
| **Estímulo** | ¿Qué ocurre? |
| **Artefacto** | ¿Sobre qué parte del sistema? |
| **Entorno** | ¿En qué condiciones? |
| **Respuesta** | ¿Qué debe hacer el sistema? |
| **Medida de la respuesta** | ¿Con qué valor se comprueba? |

Se agregan **Verificación** (cómo se probó), **Justificación de la medida** y **Estado real**. Estados: **Cumplido**, **Parcial**, **No cumplido** y **No medido**. Las medidas son las del diseño; el estado real compara con lo construido y cita la evidencia.

## EQ-01. Seguridad: acceso a información protegida

| Elemento | Descripción |
|---|---|
| **Atributo de calidad** | Seguridad (AC01) |
| **Fuente** | Usuario sin sesión, o usuario autenticado sin el permiso necesario. |
| **Estímulo** | Un padre intenta ver las notas de un estudiante que no es su hijo, o un docente intenta registrar notas en un curso que no tiene asignado. |
| **Artefacto** | Middleware `auth`, `activo` y `can:`, filtro de pertenencia (ADR-011) y servicios de Evaluación y Asistencia. |
| **Entorno** | Operación normal, con roles y permisos configurados. |
| **Respuesta** | El sistema rechaza la operación (403), no ejecuta ningún cambio y no muestra datos ajenos. |
| **Medida** | Se rechaza el **100 %** de los intentos no autorizados incluidos en las pruebas; ninguna contraseña se guarda sin hash. |
| **Verificación** | Pruebas Pest: `tests/Feature/Autorizacion/` (`RutasProtegidasTest`, `GateTest`, `MenuTest`, `SinCondicionalesDeRolTest`), `tests/Feature/*/Acceso`, `tests/Feature/*/Pertenencia` y `tests/Feature/Auth/`. Ejemplo: `Evaluacion/Acceso/RegistroAccesoTest.php`. |
| **Justificación de la medida** | Son datos de menores de edad (RC15): un solo acceso indebido ya es un incumplimiento. |
| **Estado real** | **Cumplido** en las pruebas. No existe una única matriz rol × ruta; hay matrices por módulo (p. ej. `Academico/Acceso/MatrizDeAccesoTest.php`). |

## EQ-02. Seguridad: privacidad en el asistente virtual

| Elemento | Descripción |
|---|---|
| **Atributo de calidad** | Seguridad, privacidad en IA (AC02) |
| **Fuente** | Padre de familia o alumno que usa el asistente virtual. |
| **Estímulo** | Pregunta por datos de otro estudiante, por ejemplo por su nombre, o intenta cambiar las instrucciones del asistente. |
| **Artefacto** | `AsistenteService`, `ResolverAlumno`, `ConstructorContexto` y el contexto que recibe el proveedor. |
| **Entorno** | Operación normal, con el adaptador falso (pruebas) o con OpenRouter (staging). |
| **Respuesta** | El contexto enviado contiene solo los datos de un alumno propio; el nombre ajeno no se confirma. |
| **Medida** | **0** datos de otros estudiantes, de notas no publicadas ni de identificadores personales en lo enviado, en el 100 % de los casos de prueba (spec 007, SC-002 a SC-004). |
| **Verificación** | `tests/Feature/Asistente/`: `AccesoAjenoTest`, `NoPublicadoTest`, `MinimizacionTest`, `ConstructorContextoTest`, `ResolverAlumnoTest`. `ProveedorFalso` guarda los mensajes recibidos para inspeccionarlos; `Http::preventStrayRequests()` impide llamadas externas. |
| **Justificación de la medida** | Se mide el contenido que sale del sistema y no la respuesta de la IA, porque la respuesta no es predecible (spec 007, R-13). |
| **Estado real** | **Cumplido** sobre el contexto. El tono del rechazo es comportamiento del modelo y se revisó a mano en la prueba de humo (`specs/007-asistente-virtual/evidencias/staging-t031.md`). |

## EQ-03. Rendimiento: cierre de bimestre

| Elemento | Descripción |
|---|---|
| **Atributo de calidad** | Rendimiento (AC03) |
| **Fuente** | Docentes, padres y alumnos al cierre del bimestre, simulados por k6. |
| **Estímulo** | Hasta 1000 usuarios virtuales inician sesión y consultan; las cuentas de docentes registran notas y las familias consultan notas y descargan boletas. |
| **Artefacto** | Aplicación Laravel (nginx + PHP-FPM), MySQL y Redis. |
| **Entorno** | Staging en `Standard_B2als_v2` (2 vCPU, 4 GB) con perfil `carga`; versión `v0.6.0`; asistente excluido (RC18). Sesión adicional del 25/11: `Standard_B4als_v2` (4 vCPU, 8 GiB; tamaño fijado por la cuota regional), perfil `carga2000`, versión `v1.1.0`, 2000 usuarios virtuales más 20 docentes. |
| **Respuesta** | El sistema atiende las solicitudes sin rechazarlas ni caerse y dentro de los umbrales de tiempo. |
| **Medida (diseño)** | p95 < 2 s y < 1 % de errores. Umbrales usados por endpoint: login < 3,5 s; consultas del Módulo 1 < 1,5 s; en el Módulo 5, consulta < 2 s, registro de notas < 4 s y boleta < 8 s; fallos < 1 %. |
| **Verificación** | `tests/Load/carga.js` y `tests/Load/evaluacion.js`, lanzados desde el workflow `carga` de GitHub Actions; métricas del servidor con `deploy/metrics.sh`. |
| **Justificación de la medida** | El percentil 95 muestra la experiencia de casi todos los usuarios; los umbrales distinguen la operación de cada endpoint. |
| **Estado real** | **No cumplido en p95.** `evaluacion.js` (1020 VUs): login 15,34 s, consulta 13,89 s, notas 14,43 s, boleta 16,40 s. `carga.js` corregido (1000 VUs): login 5,52 s y consultas 3,39 s. **Solicitudes fallidas: 0 %** en ambas. La CPU del contenedor `app` saturó los 2 vCPU y la memoria sobró. Límite práctico estimado: ~500 usuarios (inferido, no medido). Evidencia: `specs/006-evaluacion-boletas/evidencias/k6-sesion-2026-11-13.md`. Las sesiones de octubre de `specs/001-auth-roles-rbac/evidencias/` se conservan con la advertencia del hallazgo A2: sus «consultas» medían la página de login. **Sesión del 25/11** (`evaluacion.js`, hasta 2020 VUs, 4 vCPU): tampoco cumplida; login 15,70 s, consulta 13,65 s, notas 10,16 s y boleta 15,33 s; 0,00 % de solicitudes fallidas (0 de 49 367) y CPU del servidor al 100 %: [`k6-sesion-2026-11-25.md`](../../specs/006-evaluacion-boletas/evidencias/k6-sesion-2026-11-25.md). |

## EQ-04. Disponibilidad: periodos críticos y reinicios

| Elemento | Descripción |
|---|---|
| **Atributo de calidad** | Disponibilidad (AC04) |
| **Fuente** | Muchos usuarios generando boletas a la vez, o un reinicio del servidor. |
| **Estímulo** | Se piden boletas PDF durante la carga, o la VM se reinicia. |
| **Artefacto** | Contenedores de Docker Compose y la generación síncrona de la boleta. |
| **Entorno** | Staging. |
| **Respuesta (diseño)** | La generación de boletas no bloquea las demás pantallas; tras un reinicio, los contenedores se levantan solos. |
| **Medida (diseño)** | Mientras se procesan las boletas, las demás pantallas siguen cumpliendo EQ-03; tras un reinicio, el sistema responde en 2 minutos o menos sin intervención manual. |
| **Verificación** | Reinicio de la VM desde el portal y revisión de los contenedores (`despliegue-y-vuelta-atras.md`); boletas durante la rampa de `evaluacion.js`. |
| **Estado real** | **Parcial.** Reinicio: `app`, `db`, `redis` y `web` estaban en marcha a los 3 minutos, sin intervención (`restart: unless-stopped`); no se cronometró el instante exacto. **Boletas: no se aíslan** (no hay cola ni worker, ADR-007): se generan en la solicitud y durante la carga la CPU llegó al 100 %. 516 boletas válidas en 14,5 min, 0 fallos. |

## EQ-05. Mantenibilidad: cambios sin afectar otros módulos

| Elemento | Descripción |
|---|---|
| **Atributo de calidad** | Mantenibilidad (AC05) |
| **Fuente** | Desarrollador. |
| **Estímulo** | Se cambia una regla de evaluación, se agrega una escala de calificación o se reemplaza el proveedor de IA. |
| **Artefacto** | Módulo responsable del cambio (Evaluación o Asistente). |
| **Entorno** | Desarrollo, con pruebas automatizadas. |
| **Respuesta** | El cambio se concentra en el módulo responsable. |
| **Medida (diseño)** | 0 archivos modificados fuera del módulo responsable; pruebas del módulo y de regresión en verde; 0 consultas a la base de datos desde controladores, componentes Livewire y vistas. |
| **Verificación (diseño)** | `git diff --stat` y pruebas `arch()` de Pest. |
| **Estado real** | **Parcial.** M-03 (20/11) sobre el retoque R-01 (`7d4b53c..a1e0114`, 5 archivos): 0 archivos de código de otro módulo, 0 puntos de registro compartidos y 1 de `docs/`; 2 archivos de `app/Support` quedaron sin clasificar por la regla (serían 2 si se contaran como código de otro módulo). **Es una sola muestra.** No hay pruebas `arch()` (búsqueda en `tests/` sin resultados) y no se midieron las consultas desde controladores, componentes Livewire y vistas. Indicios: agregar una escala es agregar datos (`escalas_calificacion`); cambiar de proveedor de IA es implementar `ProveedorIA` y registrarlo en `AppServiceProvider` (spec 007, R-11). Evidencia: [`docs/retoques.md`](../retoques.md), M-03. |

## EQ-06. Adaptabilidad: configurar una nueva institución

| Elemento | Descripción |
|---|---|
| **Atributo de calidad** | Adaptabilidad / configurabilidad (AC06) |
| **Fuente** | Administrador de un colegio nuevo. |
| **Estímulo** | Configura su institución con otros niveles, cantidad de grados y secciones, periodos y escala. |
| **Artefacto** | Gestión Académica y los catálogos de datos base. |
| **Entorno** | Instalación nueva. |
| **Respuesta** | El sistema queda listo para matricular y evaluar con esa estructura. |
| **Medida** | 0 cambios en el código fuente. El diseño proponía además configurar en 1 hora o menos. |
| **Verificación** | `GenericidadTest` en `tests/Feature/Academico`, `Matricula`, `Asistencia` y `Evaluacion`; `RecorridoQuickstartTest` en los módulos 2 a 4; verificación manual en staging. |
| **Estado real** | **Cumplido** en código (0 cambios, nombres como datos). **El tiempo de configuración no se cronometró** (constitución 2.2.0). La ficha de institución no tiene logo. |

## EQ-07. Testeabilidad: pruebas antes de cada despliegue

| Elemento | Descripción |
|---|---|
| **Atributo de calidad** | Testeabilidad (AC07) |
| **Fuente** | Desarrollador. |
| **Estímulo** | Ejecuta la suite de pruebas antes de integrar o desplegar. |
| **Artefacto** | Servicios y reglas de Evaluación, Matrícula, Asistencia, Autorización y Asistente. |
| **Entorno** | Máquina de desarrollo o CI, con la base `sge_testing` en MySQL y sin servicios externos. |
| **Respuesta** | Las reglas de negocio se prueban de forma automática; el proveedor de IA se reemplaza por `ProveedorFalso` o `Http::fake()`. |
| **Medida (diseño)** | 100 % de las reglas críticas con al menos una prueba; cobertura de servicios ≥ 80 %; suite en menos de 2 minutos. |
| **Verificación** | `pest --ci` y `pint --test` en `.github/workflows/ci.yml` en cada push y pull request. |
| **Estado real** | **Cumplido.** M-02 (20/11, local, PCOV): **97,5 % de cobertura de líneas en la capa de servicios** (1153 de 1183 líneas; meta ≥ 80 %) y 951 pruebas en verde. La duración cumple la meta de menos de 2 minutos: el job `pruebas` del CI tardó 64,18 s con 951 pruebas ([ejecución 45248630708](https://github.com/JorgeBerroccal/Sistema-de-Gestion-Escolar/actions/runs/45248630708)); con PCOV la misma suite tardó 95,5 s (y 128 s en una ejecución anterior), por eso la cobertura va en un job aparte. Reglas críticas, cada una con al menos una prueba: **escala** (`tests/Feature/Seeders/EscalasCalificacionSeederTest.php`; equivalentes literal y vigesimal y validación de valores en `tests/Unit/Evaluacion/CalculoPromediosTest.php`; **rechazo de una nota fuera de la escala configurada** en `tests/Feature/Evaluacion/GuardarNotasTest.php`, «es todo o nada: una celda fuera de escala no guarda ninguna…» y «con escala literal rechaza «Z» y acepta las letras de la escala»); **guardado todo o nada** (`GuardarNotasTest.php`, la prueba anterior: con una celda inválida no se guarda ninguna); **promedio** (`CalculoPromediosTest.php`: ponderado, renormalizado, nota 0, redondeo, anual); **matrícula** (`tests/Feature/Matricula/Matriculas/MatricularTest.php`: sin doble matrícula activa, incluso por concurrencia); **traslado** (`CambiarEstadoTest.php`: «traslada sin motivo», fecha, solo matrícula activa; es el estado `Trasladado` de `MatriculaService::trasladar()`); **filtro de la IA** (`tests/Feature/Asistente/AccesoAjenoTest.php` y `MinimizacionTest.php`). **La cobertura se mide en el CI** (job `cobertura`, PCOV, sin umbral, informe como artefacto): [ejecución 45248630708](https://github.com/JorgeBerroccal/Sistema-de-Gestion-Escolar/actions/runs/45248630708), 95,98 % de líneas en todo `app/` (2481 de 2585). Limitaciones: es cobertura de líneas, no de la calidad de las aserciones; la tabla por carpeta (97,5 % en servicios) es la de M-02; la base de pruebas es MySQL, no SQLite. Evidencia: [`docs/retoques.md`](../retoques.md), M-02. |

## EQ-08. Usabilidad: consulta desde el celular

| Elemento | Descripción |
|---|---|
| **Atributo de calidad** | Usabilidad (AC08) |
| **Fuente** | Padre de familia. |
| **Estímulo** | Quiere ver las notas y la boleta de su hijo desde el celular. |
| **Artefacto** | Vistas Blade + Livewire. |
| **Entorno** | Navegador móvil de 360 px de ancho. |
| **Respuesta** | Encuentra y descarga la boleta sin ayuda. |
| **Medida (diseño)** | 90 % de tareas completadas por 5 usuarios; boleta en 3 interacciones o menos; sin desplazamiento horizontal a 360 px. |
| **Estado real** | **Parcial.** M-01 (20/11, Playwright con Chromium a 360 px, no un celular real): sin desplazamiento horizontal en Inicio, Inicio con el menú abierto, `/matricula/mis-alumnos`, `/asistencia/mia`, `/notas/mias`, `/asistente` y `/mi-cuenta/password`. La meta de **3 interacciones o menos** hasta la boleta **se cumple por las tarjetas del Inicio (2)**; el menú lateral (4) es un camino alternativo. Queda **parcial solo por la prueba con 5 usuarios**, que es parte de la medida y no se hizo. Evidencia: [`docs/retoques.md`](../retoques.md), M-01. |

## EQ-09. Portabilidad: despliegue en un servidor nuevo

| Elemento | Descripción |
|---|---|
| **Atributo de calidad** | Portabilidad / desplegabilidad (AC09) |
| **Fuente** | Responsable del despliegue. |
| **Estímulo** | Necesita instalar el SGE en una VM nueva o desplegar una versión nueva. |
| **Artefacto** | `docker-compose.prod.yml`, `deploy/` y la guía `docs/despliegue/`. |
| **Entorno** | Servidor Linux con Docker. |
| **Respuesta** | El sistema queda funcionando con los mismos servicios que en desarrollo. |
| **Medida (diseño)** | Despliegue con las migraciones en 30 minutos o menos siguiendo solo la guía. |
| **Verificación** | Despliegue y vuelta atrás de `v0.2.0` (`specs/002-despliegue-ci-staging/evidencias/despliegue-y-vuelta-atras.md`); guía de primer despliegue. |
| **Estado real** | **Parcial.** Despliegue de una versión: 27 s; vuelta atrás: 22 s; recuperación de una migración fallida: 4 min 7 s. El aprovisionamiento completo de una VM desde cero con la guía **no se cronometró**. |

## EQ-10. Trazabilidad: auditoría de acciones críticas

| Elemento | Descripción |
|---|---|
| **Atributo de calidad** | Trazabilidad / auditoría (AC10) |
| **Fuente** | Docente o administrador. |
| **Estímulo** | Un docente modifica una nota, o un administrador desactiva una cuenta. |
| **Artefacto** | Columnas `registrado_por` y `cerrado_por`. |
| **Entorno** | Operación normal. |
| **Respuesta (diseño)** | Queda un registro de quién hizo el cambio, cuándo y qué cambió. |
| **Medida (diseño)** | El 100 % de las acciones críticas (inicio de sesión, notas, gestión de usuarios) genera un registro con usuario, fecha y hora, acción, valor anterior y valor nuevo. |
| **Estado real** | **No cumplido.** Solo se guarda quién escribió por última vez cada nota y asistencia y quién cerró cada periodo, con marcas de tiempo. No hay registro de inicios de sesión, de gestión de usuarios ni de valores anteriores (ADR-010 no implementada). |

## Resumen de escenarios

| Código | Atributo | Qué se busca comprobar | Atributo de origen | Driver | Estado real |
|--------|----------|------------------------|:------------------:|:------:|-------------|
| EQ-01 | Seguridad | Que nadie acceda a información o acciones que su rol no permite. | AC01 | DA01 | Cumplido |
| EQ-02 | Privacidad en IA | Que el asistente nunca reciba datos de otros estudiantes. | AC02 | DA02 | Cumplido |
| EQ-03 | Rendimiento | Que el sistema soporte 1000 usuarios dentro de los umbrales de p95. | AC03 | DA03 | **No cumplido** en p95; 0 % de fallos |
| EQ-04 | Disponibilidad | Que las boletas no bloqueen el sistema y que se recupere solo. | AC04 | DA03 | Parcial |
| EQ-05 | Mantenibilidad | Que un cambio quede dentro de su módulo. | AC05 | DA09 | Parcial |
| EQ-06 | Adaptabilidad | Que un colegio nuevo se configure sin tocar el código. | AC06 | DA04 | Cumplido (tiempo no medido) |
| EQ-07 | Testeabilidad | Que las reglas de negocio tengan pruebas automatizadas. | AC07 | DA07 | Cumplido |
| EQ-08 | Usabilidad | Que un padre use el sistema desde el celular. | AC08 | — | Parcial |
| EQ-09 | Portabilidad | Que el sistema se despliegue en cualquier servidor con Docker. | AC09 | DA06 | Parcial |
| EQ-10 | Trazabilidad | Que toda acción crítica quede registrada. | AC10 | DA08 | No cumplido |

Los estados de este resumen juzgan la **medida** de cada escenario; los de [`04`](04-atributos-de-calidad.md) juzgan el **atributo**. Difieren en dos casos, explicados en la sección «Relación con los escenarios» de ese documento: EQ-09 (atributo cumplido, medida parcial) y EQ-10 (atributo parcial, medida no cumplida).

## Cómo se verificó

| Punto | Comprobación | Resultado |
|-------|--------------|-----------|
| Pruebas `arch()` (EQ-05) | Búsqueda de `arch(` y expectativas de arquitectura de Pest en `tests/` | Sin coincidencias. |
| Cambio dentro de su módulo (EQ-05) | `git diff --stat 7d4b53c..a1e0114` (retoque R-01), con archivos en tres grupos | 0 de otro módulo; 0 de registro compartido; 1 de `docs/`; 2 de `app/Support` sin clasificar. |
| Lista de pruebas citadas (EQ-01, EQ-02, EQ-06) | Listado de `tests/Feature/` y `tests/Feature/*/Acceso` | Los archivos nombrados existen. |
| Resultados de carga (EQ-03) | Lectura de `k6-sesion-2026-11-13.md`, `k6-sesion-2026-11-25.md` y del bloque SC-006 de la spec 001 | Cifras copiadas de las tablas de resultados de esos documentos. |
| Reinicio y despliegue (EQ-04, EQ-09) | Lectura de `despliegue-y-vuelta-atras.md`, `migracion-fallida.md` y `docker-compose.prod.yml` | Tiempos y política de reinicio como se citan. |
| Cobertura y CI (EQ-07) | Job `cobertura` de `.github/workflows/ci.yml` y cobertura local con PCOV (M-02) | CI: 95,98 % de líneas en `app/` ([ejecución 45248630708](https://github.com/JorgeBerroccal/Sistema-de-Gestion-Escolar/actions/runs/45248630708)); local: 97,5 % en servicios, 951 pruebas. |
| Usabilidad a 360 px (EQ-08) | Playwright con Chromium a 360 px y `padre.demo` (M-01) | `scrollWidth` = 360 en siete pantallas; boleta en 2 interacciones (tarjetas) o 4 (menú). |
| Número de pruebas y duración (EQ-07) | Registro del job `pruebas` de la ejecución 45248630708 (`gh api …/actions/jobs/<id>/logs`) | `Tests: 951 passed (3971 assertions)`, `Duration: 64.18s`. |
