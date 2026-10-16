# Feature Specification: Despliegue en servidor staging e integración continua

**Feature Branch**: `002-despliegue-ci-staging`

**Created**: 2026-10-16

**Status**: Draft

**Módulo MVP**: Transversal — infraestructura que sirve a los seis módulos del MVP. Aprobada
explícitamente por el desarrollador según el Principio IV de la constitución y requisito del curso.
No agrega funcionalidad de negocio.

**Input**: User description: "Despliegue del SGE en un servidor real e integración continua. El
Módulo 1 está terminado (etiqueta v0.1.0). Este feature no agrega funcionalidad: lo pone en un
servidor accesible por internet (máquina virtual Linux x86 en Azure for Students, entorno staging),
con HTTPS válido sobre el nombre DNS gratuito de Azure, la misma pila de contenedores con
configuración de producción, secretos solo en el servidor, despliegue repetible con vuelta atrás,
integración continua en GitHub (suite de pruebas y estilo de código en cada push y pull request),
control de costo con cambio temporal de tamaño y alertas de presupuesto, prueba de carga de 1000
usuarios virtuales lanzada bajo demanda con evidencia guardada, verificación manual y medición de
SC-007 en el servidor, y respaldo de la base de datos con restauración documentada."

## Contexto y alcance

- Este feature es **infraestructura transversal** que sirve a los seis módulos del MVP; el
  desarrollador la aprobó explícitamente según el Principio IV de la constitución y es además un
  requisito del curso. No introduce funcionalidad de negocio. Uno de sus objetivos es cerrar el
  criterio **SC-006** del Módulo 1, hoy pendiente porque solo se ejecutó la prueba de humo local;
  eso no la clasifica como parte del Módulo 1.
- El servidor es un entorno **staging** (`APP_ENV=staging`): no contiene datos reales de ningún
  colegio y es el único lugar donde se permite la prueba de carga completa (nunca en producción).
- **Fuera de alcance**: dominio propio pagado, alta disponibilidad, más de un servidor, monitoreo
  avanzado, y cualquier funcionalidad de los Módulos 2–6.
- La creación de la máquina virtual y de los recursos de Azure la realiza el desarrollador desde el
  portal; este feature solo especifica con precisión qué configurar. El asistente de código **no**
  crea recursos en Azure.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Sistema accesible por internet con HTTPS válido (Priority: P1)

El desarrollador (y el evaluador del curso) abre la dirección pública del SGE desde cualquier
navegador, ve el candado de conexión segura sin advertencias, inicia sesión con el Administrador
inicial y navega el sistema como lo hace en local.

**Why this priority**: Sin un servidor accesible no existe nada que probar, medir ni demostrar; es
la base de todas las demás historias.

**Independent Test**: Con el servidor aprovisionado, abrir la URL pública en un navegador externo a
la red del desarrollador, verificar el certificado, iniciar sesión como Administrador y ver el menú.

**Acceptance Scenarios**:

1. **Given** el servidor desplegado, **When** se abre la URL `https://` del nombre DNS asignado por
   Azure, **Then** la página de inicio de sesión carga con certificado válido emitido por una
   autoridad pública y sin advertencias del navegador.
2. **Given** la misma URL escrita con `http://`, **When** se solicita, **Then** el navegador es
   redirigido a `https://`.
3. **Given** el servidor desplegado, **When** se intenta conectar desde internet a los servicios de
   base de datos y caché, **Then** la conexión es rechazada o no responde.
4. **Given** un certificado próximo a vencer, **When** llega la ventana de renovación, **Then** se
   renueva sin intervención manual y sin interrumpir el servicio.
5. **Given** el Administrador inicial sembrado en el servidor, **When** inicia sesión, **Then** accede
   a `/inicio` con el menú esperado del rol.

---

### User Story 2 - Integración continua visible en GitHub (Priority: P1)

Cada vez que el desarrollador sube cambios o abre un pull request, GitHub ejecuta automáticamente la
suite completa de pruebas (con base de datos y caché reales) y la verificación de estilo de código, y
muestra el resultado (verde o rojo) junto al commit o pull request.

**Why this priority**: Protege el Módulo 1 de regresiones durante el resto del proyecto y
materializa el Principio V (pruebas obligatorias). Es independiente del servidor.

**Independent Test**: Subir un commit con una prueba rota y otro con estilo incorrecto; verificar que
GitHub marca el fallo, y que un commit correcto queda en verde.

**Acceptance Scenarios**:

1. **Given** un push a cualquier rama, **When** termina la ejecución automática, **Then** GitHub
   muestra el estado de las pruebas y del estilo de código asociado al commit.
2. **Given** un pull request abierto, **When** alguna prueba falla, **Then** el pull request muestra
   el fallo con acceso al detalle de qué prueba falló.
3. **Given** código que incumple el estilo del proyecto, **When** corre la verificación, **Then**
   falla y señala los archivos infractores sin modificar el repositorio.
4. **Given** una ejecución exitosa, **When** se revisa, **Then** corrió el 100 % de las pruebas
   existentes (no un subconjunto) contra una base de datos MySQL y un Redis reales.

---

### User Story 3 - Despliegue repetible y vuelta atrás (Priority: P1)

El desarrollador publica una versión nueva en el servidor siguiendo un procedimiento documentado, y
si la versión falla puede regresar a la anterior (por ejemplo, a una etiqueta de Git) siguiendo otro
procedimiento igualmente documentado.

**Why this priority**: Un despliegue manual no reproducible es la principal fuente de fallos antes de
una sustentación; la vuelta atrás es la red de seguridad.

**Independent Test**: Desplegar una versión etiquetada, luego una posterior con un cambio visible,
verificarlo, y volver a la primera comprobando que el cambio desaparece y los datos persisten.
(La etiqueta `v0.1.0` es anterior a la imagen de producción y no tiene imagen publicada; las dos
versiones de la prueba serán las primeras publicadas por el nuevo proceso.)

**Acceptance Scenarios**:

1. **Given** una etiqueta o commit de Git, **When** se ejecuta el procedimiento de despliegue,
   **Then** el servidor sirve exactamente esa versión, con la configuración de producción de la
   constitución (OPcache activo, `php artisan optimize` ejecutado) y migraciones aplicadas.
2. **Given** una versión desplegada con fallo, **When** se ejecuta el procedimiento de vuelta atrás
   indicando la etiqueta anterior, **Then** el servidor vuelve a servir esa versión.
3. **Given** un despliegue o vuelta atrás, **When** concluye, **Then** los datos de la base de datos
   y las sesiones válidas previas no se pierden salvo que una migración lo implique explícitamente.
4. **Given** un servidor limpio, **When** se sigue la documentación desde cero, **Then** queda
   operativo sin pasos no documentados (constitución: "sin pasos manuales no documentados").
5. **Given** el repositorio completo, **When** se inspecciona (incluido su historial nuevo), **Then**
   no contiene `APP_KEY`, contraseñas de base de datos ni `SGE_ADMIN_PASSWORD`; viven solo en el
   archivo de entorno del servidor.
6. **Given** el servidor desplegado, **When** se inspeccionan los contenedores, **Then** el código
   proviene de la imagen/versión desplegada y no de un directorio montado desde el equipo del
   desarrollador.

---

### User Story 4 - Prueba de carga de 1000 usuarios con evidencia (Priority: P2)

El desarrollador cambia temporalmente la máquina a un tamaño de CPU sostenida de 2 vCPU, lanza desde
GitHub la prueba de carga (rampa 100 → 300 → 600 → 1000 usuarios virtuales) contra la URL del
servidor, y obtiene guardados como evidencia el resumen de resultados, el tamaño de máquina usado y la
CPU/memoria del servidor durante la prueba. Después devuelve la máquina al tamaño económico.

**Why this priority**: Cierra SC-006 del Módulo 1 y el requisito de la constitución de pruebas de
carga documentadas antes de dar por listos los endpoints críticos.

**Independent Test**: Ejecutar la prueba bajo demanda tras el cambio de tamaño y comprobar que
aparecen resumen, tamaño de máquina y series de CPU/memoria como evidencia descargable y archivada
en el repositorio.

**Acceptance Scenarios**:

1. **Given** el servidor en staging con los 1000 usuarios de prueba sembrados, **When** el
   desarrollador lanza manualmente la prueba desde GitHub, **Then** la ejecución corre la rampa
   completa contra la URL pública y no se inicia sola con push ni pull request.
2. **Given** una ejecución terminada, **When** se revisa la evidencia, **Then** contiene el resumen
   de resultados con los umbrales de SC-006 evaluados, el tamaño de la máquina y la CPU/memoria del
   servidor durante la prueba.
3. **Given** un servidor con `APP_ENV` distinto de staging, **When** se intenta sembrar usuarios de
   carga o lanzar la prueba completa, **Then** la operación se rechaza.
4. **Given** el tamaño económico de serie con CPU limitada, **When** se documenta el procedimiento,
   **Then** indica cómo cambiar al tamaño de 2 vCPU sin CPU limitada, verificar el servicio, ejecutar
   la prueba y regresar al tamaño económico, con el costo estimado de cada paso.
5. **Given** el resultado de la prueba, **When** los umbrales no se cumplen, **Then** la evidencia
   igualmente se guarda y queda registrado el incumplimiento (no se oculta).

---

### User Story 5 - Control de costo del crédito (Priority: P2)

El desarrollador recibe avisos cuando el gasto alcanza el 50 %, 75 % y 90 % del crédito de 100 USD,
y dispone de una estimación documentada que demuestra que el crédito alcanza hasta diciembre de 2026.

**Why this priority**: Agotar el crédito antes de la sustentación dejaría el sistema sin servidor.

**Independent Test**: Verificar en el portal que existen las tres alertas con destinatario válido y
que la estimación de costo documentada cabe en el crédito con margen.

**Acceptance Scenarios**:

1. **Given** la suscripción configurada, **When** se revisan las alertas de presupuesto, **Then**
   existen avisos al 50 %, 75 % y 90 % del crédito enviados al correo del desarrollador.
2. **Given** la estimación documentada, **When** se suma el uso diario económico, las horas del
   tamaño de carga y almacenamiento/IP, **Then** el total hasta el 31 de diciembre de 2026 no supera
   el 80 % del crédito.
3. **Given** el servidor sin uso (fuera de pruebas o demostraciones), **When** se documenta el
   procedimiento, **Then** indica cómo detenerlo liberando el cómputo para no consumir crédito y
   cómo reactivarlo sin perder datos ni cambiar la URL pública.

---

### User Story 6 - Verificación manual y SC-007 en el servidor (Priority: P2)

El desarrollador repite en el servidor la verificación manual del Módulo 1 (quickstart §3) y mide
SC-007 (crear una cuenta con rol en menos de 1 minuto), registrando cada intento y el peor caso.

**Why this priority**: Demuestra que el sistema se comporta igual en un entorno real que en local.

**Independent Test**: Completar los 11 pasos de §3 y al menos 5 mediciones de SC-007 en la URL
pública y comprobar el registro de evidencia.

**Acceptance Scenarios**:

1. **Given** el servidor desplegado, **When** se ejecutan los 11 pasos de la verificación manual,
   **Then** cada paso queda registrado como cumplido o fallido con su observación.
2. **Given** la medición de SC-007, **When** se realizan los intentos, **Then** se registra el tiempo
   de cada uno y el peor caso, y se declara si el peor caso es inferior a 60 s.

---

### User Story 7 - Respaldo y restauración de la base de datos (Priority: P3)

El desarrollador dispone de un respaldo de la base de datos del servidor y de un procedimiento
probado para restaurarlo, de modo que un daño o una vuelta atrás con migraciones no destruyan la
información de staging.

**Why this priority**: Protege el trabajo acumulado; es menos urgente porque staging no tiene datos
reales.

**Independent Test**: Generar un respaldo, alterar o borrar datos, restaurar y verificar que el
sistema recupera el estado del respaldo.

**Acceptance Scenarios**:

1. **Given** el servidor en operación, **When** se ejecuta el procedimiento de respaldo (o corre el
   programado), **Then** se genera un archivo de respaldo fechado, fuera del contenedor de base de
   datos, que también puede copiarse al equipo del desarrollador.
2. **Given** un respaldo válido, **When** se sigue el procedimiento de restauración documentado,
   **Then** la base de datos queda con los datos del respaldo y el Administrador puede iniciar sesión.
3. **Given** una restauración, **When** se verifica, **Then** se comprobó al menos una vez en el
   servidor y quedó el resultado registrado.
4. **Given** el uso continuo, **When** pasan los días, **Then** los respaldos con más de 7 días se
   eliminan para no llenar el disco.

---

### Edge Cases

- ¿Qué pasa si la emisión del certificado falla (límite de la autoridad, DNS aún no propagado)? El
  procedimiento debe indicar cómo diagnosticar y reintentar sin dejar el sitio caído; mientras tanto
  el sistema no debe servir credenciales por HTTP sin cifrar.
- ¿Qué pasa si la IP pública cambia al detener y reiniciar la máquina? La dirección debe ser estática
  y el nombre DNS estable para que el certificado y la URL de la prueba sigan siendo válidos.
- ¿Qué pasa si una migración falla a mitad de un despliegue? El procedimiento debe indicar cómo
  detectar el fallo, volver a la versión anterior y, si hace falta, restaurar el respaldo previo; por
  eso se respalda antes de cada despliegue.
- ¿Qué pasa si la prueba de carga agota CPU o memoria y el servidor deja de responder? La evidencia
  debe conservar lo medido hasta ese punto y el procedimiento indicar cómo recuperar el servicio.
- ¿Qué pasa si la máquina de tamaño de carga se olvida encendida? Las alertas de presupuesto y el
  procedimiento de vuelta al tamaño económico (con recordatorio en la lista de verificación) lo
  cubren.
- ¿Qué pasa si la región elegida no ofrece el tamaño de carga o no está permitida por la suscripción
  de estudiante? La documentación debe indicar cómo verificarlo antes de crear la máquina y la región
  alternativa más cercana.
- ¿Qué pasa si los secretos de GitHub necesarios para la prueba bajo demanda no están configurados?
  La ejecución falla pronto con un mensaje claro, sin exponer valores.
- ¿Qué pasa si un pull request proviene de una bifurcación? No debe tener acceso a secretos ni al
  servidor.
- ¿Qué pasa con la memoria limitada (1 GB) de la máquina económica? El servidor nunca construye
  imágenes ni instala dependencias; usa swap y una configuración reducida de los servicios.

## Requirements *(mandatory)*

### Functional Requirements

**Servidor y acceso**

- **FR-001**: El sistema MUST estar accesible por internet mediante `https://` en el nombre DNS
  gratuito asignado por Azure a la IP pública de la máquina (`*.cloudapp.azure.com`), sin comprar
  dominio.
- **FR-002**: El sitio MUST presentar un certificado válido de una autoridad pública (Let's Encrypt),
  redirigir todo `http://` a `https://`, y renovar el certificado automáticamente.
- **FR-003**: La documentación MUST especificar con precisión la configuración que el desarrollador
  aplica en el portal de Azure: suscripción y región (una permitida por Azure for Students y lo más
  cercana posible a Perú, con alternativa), imagen Ubuntu LTS x86, tamaño económico diario (serie B),
  tamaño de carga (no burstable, 2 vCPU, memoria suficiente para la pila), tipo y tamaño de disco, IP
  pública estática, etiqueta de nombre DNS, y reglas de red.
- **FR-004**: Solo MUST estar abierto a internet: SSH (22) únicamente con clave pública, sin
  contraseña y con bloqueo automático de intentos fallidos (fail2ban); no se limita por IP de origen
  porque la conexión del desarrollador tiene IP dinámica; HTTP (80) para el desafío y la
  redirección, y HTTPS (443). La base de datos, la caché y cualquier otro puerto interno MUST NOT ser
  accesibles desde internet.
- **FR-005**: El procedimiento MUST incluir el endurecimiento básico del servidor (usuario no raíz con
  clave, cortafuegos, fail2ban, actualizaciones de seguridad automáticas) y memoria de intercambio
  (swap) de 2 GB por la poca RAM del tamaño diario.

**Pila y configuración**

- **FR-006**: El despliegue MUST usar la misma pila de Docker Compose del proyecto (nginx, PHP-FPM,
  MySQL, Redis) con la configuración de producción de la constitución: OPcache activo y
  `php artisan optimize` ejecutado en cada despliegue; `APP_ENV=staging`, `APP_DEBUG` desactivado.
- **FR-007**: En el servidor, el código MUST provenir de la versión desplegada (imagen de producción
  construida por la integración continua y publicada en GitHub Container Registry, etiquetada por
  versión) y MUST NOT montarse desde un directorio de desarrollo; las herramientas de desarrollo no
  deben estar en la imagen de producción. El servidor MUST NOT construir imágenes ni ejecutar
  gestores de dependencias (por su RAM de 1 GB).
- **FR-008**: Los datos de MySQL y Redis MUST persistir entre reinicios y despliegues, y esos
  servicios MUST NOT publicar puertos hacia el exterior de la máquina.
- **FR-009**: Los secretos (`APP_KEY`, contraseñas de base de datos, `SGE_ADMIN_PASSWORD` y los demás
  de `.env.example`) MUST existir solo en el archivo de entorno del servidor (con permisos
  restringidos) y MUST NOT estar en el repositorio. `.env.example` MUST reflejar todas las variables
  necesarias sin valores reales.
- **FR-010**: La carga inicial del servidor (roles, permisos, Administrador inicial) MUST ejecutarse
  con el procedimiento documentado del Módulo 1; la contraseña del Administrador MUST ser fuerte y
  distinta de cualquier valor de ejemplo.

**Despliegue y vuelta atrás**

- **FR-011**: MUST existir un procedimiento documentado y repetible, ejecutado por el desarrollador
  por SSH mediante un script que descarga la imagen de la etiqueta indicada (GitHub no necesita
  acceso al servidor), para desplegar una versión identificada por etiqueta de Git, que aplique migraciones, regenere cachés de
  configuración/rutas/vistas y verifique que el sitio responde antes de darlo por exitoso.
- **FR-012**: MUST existir un procedimiento documentado para volver a una versión anterior, que
  consiste en desplegar la etiqueta anterior, y su límite conocido (migraciones destructivas requieren restaurar un respaldo).
- **FR-013**: Cada despliegue MUST registrar qué versión quedó activa (etiqueta/commit y fecha) de
  forma consultable en el servidor.
- **FR-014**: El procedimiento MUST generar un respaldo de la base de datos antes de aplicar
  migraciones.

**Integración continua**

- **FR-015**: En cada push y cada pull request MUST ejecutarse automáticamente en GitHub la suite
  completa de pruebas de Pest contra una base de datos MySQL 8 y un Redis reales, y la verificación de
  estilo de código (Pint) en modo de solo comprobación.
- **FR-016**: Un fallo de pruebas o de estilo MUST ser visible en GitHub (estado del commit y del pull
  request) con el detalle accesible. La documentación MUST indicar cómo exigir que el estado sea verde
  antes de integrar a la rama principal.
- **FR-017**: La integración continua MUST NOT requerir secretos del servidor ni acceder a él, y los
  pull requests de bifurcaciones MUST NOT recibir secretos.

**Prueba de carga**

- **FR-018**: La prueba de carga completa (rampa 100 → 300 → 600 → 1000 usuarios virtuales, umbrales de
  SC-006) MUST poder lanzarse manualmente, bajo demanda, desde GitHub, contra la URL del servidor, y
  MUST NOT ejecutarse automáticamente con push o pull request.
- **FR-019**: La ejecución MUST guardar como evidencia: el resumen completo de resultados con la
  evaluación de cada umbral, la URL objetivo, la versión desplegada, el tamaño de la máquina, y las
  series de uso de CPU y memoria del servidor durante la prueba. Como GitHub no tiene acceso al
  servidor, el resumen queda como archivo descargable de la ejecución y las series de CPU/memoria y
  el tamaño de máquina los registra un script en el servidor durante la prueba; el desarrollador
  reúne ambos y los registra en el repositorio en `specs/001-auth-roles-rbac/evidencias/`.
- **FR-020**: La siembra de usuarios de carga y la prueba completa MUST rechazarse cuando el entorno
  no sea staging (o local para la prueba de humo).
- **FR-021**: La documentación MUST describir el procedimiento completo de la prueba: cambiar a
  tamaño no burstable de 2 vCPU, esperar que el servicio vuelva, sembrar usuarios, lanzar la prueba,
  recoger evidencia y regresar al tamaño económico. El generador de carga MUST ejecutarse fuera del
  servidor probado.
- **FR-022**: Si los umbrales de SC-006 no se cumplen, el resultado y el análisis MUST registrarse; el
  criterio solo se declara cumplido con evidencia de una ejecución que lo satisfaga.

**Costo**

- **FR-023**: MUST configurarse alertas de presupuesto al 50 %, 75 % y 90 % del crédito de 100 USD,
  con notificación por correo, y documentarse cómo verificarlas.
- **FR-024**: La documentación MUST incluir una estimación de costo (cómputo diario, horas del tamaño
  de carga, disco, IP pública y tráfico) hasta el 31 de diciembre de 2026, con las suposiciones de
  precio y fecha de consulta, y el procedimiento para detener y reactivar la máquina sin cambiar la
  URL ni perder datos.
- **FR-025**: La documentación MUST incluir el procedimiento de cambio de tamaño en ambos sentidos,
  incluida su duración esperada, la verificación posterior y la comprobación de disponibilidad del
  tamaño en la región.

**Verificación en el servidor**

- **FR-026**: MUST repetirse en el servidor la verificación manual del Módulo 1 (quickstart §3, 11
  pasos) y registrarse el resultado de cada paso con fecha, versión desplegada y observaciones.
- **FR-027**: MUST medirse SC-007 en el servidor con al menos 5 intentos, registrando el tiempo de
  cada intento y el peor caso, en un registro análogo al del Módulo 1.

**Respaldo**

- **FR-028**: MUST existir un respaldo de la base de datos, manual y programado (al menos diario),
  guardado fuera del contenedor de base de datos, con retención de 7 días, y con una forma documentada
  de copiarlo fuera del servidor.
- **FR-029**: MUST existir un procedimiento de restauración documentado y comprobado al menos una vez
  en el servidor, con el resultado registrado.

**Documentación**

- **FR-030**: Toda la operación (aprovisionamiento, primer despliegue, despliegue regular, vuelta
  atrás, certificado, cambio de tamaño, prueba de carga, respaldo/restauración, detener/reactivar)
  MUST quedar en documentación del repositorio, en español, y la guía de inicio del Módulo 1 MUST
  enlazar a ella en lugar de usar direcciones de ejemplo.

### Key Entities

- **Entorno staging**: máquina virtual única con sus recursos asociados (IP estática, nombre DNS,
  disco, reglas de red); atributos relevantes: región, tamaño actual, versión desplegada.
- **Versión desplegada**: etiqueta o commit de Git activo en el servidor, con fecha de despliegue.
- **Archivo de entorno del servidor**: conjunto de secretos y variables, solo en el servidor.
- **Ejecución de integración continua**: resultado de pruebas y estilo asociado a un commit o pull
  request.
- **Evidencia de carga**: resumen de resultados, tamaño de máquina, series de CPU/memoria, versión
  probada y fecha.
- **Respaldo de base de datos**: archivo fechado con la información de staging, con retención de
  7 días.
- **Registro de verificación**: resultado por paso de la verificación manual y tiempos de SC-007 con
  su peor caso.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Desde una red ajena al desarrollador, el sistema carga por `https://` con certificado
  válido y sin advertencias, y el 100 % de las solicitudes `http://` terminan en `https://`.
- **SC-002**: Desde internet, ningún puerto distinto de 22, 80 y 443 responde en la máquina, y la
  base de datos y la caché no son alcanzables (verificado con un escaneo externo).
- **SC-003**: Una búsqueda en el repositorio no encuentra valores de `APP_KEY`, contraseñas de base de
  datos ni de `SGE_ADMIN_PASSWORD`; el 100 % de los secretos está solo en el servidor.
- **SC-004**: Desplegar una versión etiquetada sobre un servidor ya preparado toma menos de 15 minutos
  siguiendo solo la documentación, y volver a la versión anterior toma menos de 10 minutos.
- **SC-005**: En el 100 % de los push y pull requests se ejecutan la suite completa de pruebas y el
  estilo de código, y el resultado es visible en GitHub; una prueba o estilo roto deliberado se marca
  como fallido en el 100 % de los casos probados.
- **SC-006 (cierra el SC-006 del Módulo 1)**: Una ejecución de la prueba de carga de 1000 usuarios
  virtuales contra el servidor, con la máquina en tamaño de 2 vCPU no burstable, cumple: 95 % de los
  inicios de sesión en menos de 3,5 s, 95 % de las consultas autenticadas en menos de 1,5 s y menos
  del 1 % de solicitudes fallidas, y su evidencia (resultados, tamaño de máquina, CPU y memoria) queda
  archivada.
- **SC-007**: El gasto acumulado proyectado al 31 de diciembre de 2026 no supera el 80 % del crédito
  de 100 USD, y las tres alertas (50 %, 75 %, 90 %) existen y están verificadas.
- **SC-008**: Los 11 pasos de la verificación manual del Módulo 1 se cumplen en el servidor, y el peor
  caso de 5 o más mediciones de SC-007 del Módulo 1 es inferior a 60 s.
- **SC-009**: Una restauración de respaldo en el servidor devuelve la base de datos al estado
  respaldado en menos de 15 minutos, y el Administrador puede iniciar sesión después.
- **SC-010**: Un tercero puede reproducir el aprovisionamiento y el primer despliegue completo
  siguiendo únicamente la documentación, sin consultar al autor.

## Assumptions

- El desarrollador tiene una suscripción Azure for Students activa con 100 USD de crédito, vigente
  hasta el 16/10/2027, y el uso planificado termina con la sustentación (diciembre de 2026 como
  máximo).
- La política de la suscripción de estudiante restringe regiones y tamaños; la región final y los
  tamaños concretos se confirman en el portal durante el plan, con la región permitida más cercana a
  Perú como criterio y una alternativa documentada.
- El tamaño económico diario será de la serie B más pequeña que haga funcionar la pila completa de
  forma estable para demostraciones y verificación manual; el tamaño de carga será uno de 2 vCPU no
  burstable con al menos 4 GB de memoria, en consonancia con la constitución (VPS de 2 vCPU / 4 GB).
  Los modelos exactos se fijan en el plan según disponibilidad y precio vigentes.
- Los costos de cómputo se pagan solo por las horas en que la máquina está asignada; detenerla sin
  liberar el cómputo sigue facturando, por lo que el procedimiento exige liberar el cómputo.
- El repositorio está alojado en GitHub y el desarrollador puede definir secretos de Actions; el
  generador de carga se ejecuta en los servidores de GitHub, que tienen capacidad suficiente para
  1000 usuarios virtuales con una URL pública.
- El servidor se opera por una sola persona; no se requiere gestión de múltiples cuentas de
  operación.
- El correo para alertas de presupuesto es el del desarrollador.
- El staging contiene solo datos de prueba (usuarios sembrados y de carga); no se aplican requisitos
  de protección de datos personales reales, pero se mantienen buenas prácticas de secretos.
- La verificación manual y SC-007 las ejecuta el desarrollador en un navegador contra la URL pública.
- Este feature puede requerir ajustes menores no funcionales al código existente (por ejemplo,
  variables de entorno, imagen de producción, cabeceras de proxy), sin cambiar el comportamiento del
  Módulo 1 ni sus pruebas.
