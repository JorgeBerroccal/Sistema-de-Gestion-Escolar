# Research: Despliegue en staging e integración continua

Formato: Decisión / Justificación / Alternativas. Los datos de precio de cómputo son los consultados
por el desarrollador en el portal (Azure for Students, Chile Central); el resto son estimaciones o
conocimiento de la plataforma a **confirmar en el portal** (marcado ⚠).

## D-01 Región, sistema operativo y tamaños

- **Decisión**: Chile Central; Ubuntu Server 24.04 LTS x64 Gen2; diario **B2ats_v2** (2 vCPU, 1 GB,
  9,64 USD/mes); carga **B2als_v2** (2 vCPU, 4 GB, burstable, 38,40 USD/mes) solo por horas (**cambio del
  2026-10-20**; antes D2s_v5 y D2s_v4, ver D-01c).
- **Justificación**: Chile es la región con menor latencia hacia Perú entre las permitidas por la
  suscripción. B2ats_v2 es el único B asequible (B2als_v2 38,40 y B2ls_v2 42,49 USD/mes consumirían el
  crédito). B2als_v2 cumple lo exigido por la constitución (2 vCPU / 4 GB) y es el tamaño para el que se calcularon los
  umbrales de SC-006; al ser burstable, la CPU sostenida depende de los créditos (D-01c).
- **Historia (2026-10-16; vigente solo como registro, ver D-01c)**: la prueba de carga usaba originalmente **D2s_v5**
  (97,82 USD/mes). **Se descartó por falta de cuota**: la cuota de la familia **DSv5** en Chile Central
  de la suscripción de estudiante es **0** y la **solicitud automática de aumento fue rechazada**. La
  familia **DSv4** sí tiene cuota (0 de 4 usados), por lo que el tamaño de carga es **Standard_D2s_v4**
  (2 vCPU, 8 GiB, Intel, no burstable, **97,92 USD/mes** en Chile Central, ≈ 0,1341 USD/h; +0,10 USD/mes
  frente a v5, impacto despreciable en el costo total). Los procesadores de la serie Dsv4 varían según el
  host (Cascade Lake, Ice Lake, Sapphire Rapids o Emerald Rapids): `metrics.sh` registra el modelo de CPU
  en `meta-servidor.json` para que la evidencia lo documente.
- **Compatibilidad de imagen Gen2 (verificada en la documentación de Microsoft Learn, 2026-10-16)**: la
  serie **Basv2** (B2ats_v2) soporta VM Generación 1 y 2; la serie **Dsv4** (D2s_v4) soporta Generación 1
  y 2. Ambas son x86-64, **sin disco local temporal** (el swap y los datos viven en el disco del SO),
  con Standard SSD admitido, redes aceleradas y controlador SCSI. Por tanto la imagen **Ubuntu 24.04 LTS
  x64 Gen2** es compatible con los dos tamaños y el cambio se hace con *Resize* sin recrear la VM. Queda
  la confirmación práctica en el portal (la lista de tamaños de la VM debe mostrar B2als_v2) y ya no la
  corrección previa que suponía que Basv2 era solo Gen2: soporta ambas.
- **Alternativas**: B2ls_v2 (42,49 USD/mes, más caro que B2als_v2 sin ventaja), D2s_v5 / D2s_v4 / DS2_v2 (descartados,
  D-01c), D2as_v5/F2s_v2 (no consultados), Brasil Sur (más lejos, posiblemente no permitida).
- ⚠ **Verificar antes de crear la VM**: (a) *Usage + quotas* en Chile Central: cuota regional de vCPU
  ≥ 4 y cuota por familia ≥ 2 para **BASv2** (hay 10 disponibles; el cambio B2ats_v2 ↔ B2als_v2 no suma vCPU
  porque la VM es una sola); (b) que el cambio B2ats_v2 ↔ B2als_v2 aparezca
  en la lista de tamaños disponibles de la VM (ambas Gen2 x64; si no aparece, el recurso: crear la VM de
  carga desde un snapshot del disco, ver D-12 alternativa).

## D-01c Tamaño de carga: B2als_v2 (burstable) con control de créditos de CPU — **DECIDIDO** (2026-10-20)

- **Decisión**: la prueba de carga se hace cambiando la VM de **B2ats_v2** a **Standard_B2als_v2** (Basv2:
  2 vCPU, 4 GiB, **38,40 USD/mes ≈ 0,0526 USD/h**, sin disco temporal, cuota BASv2 disponible 10 vCPU) y volviendo
  después a B2ats_v2. Se descartan **todas las familias no burstable**. Es el tamaño para el que se calcularon
  los umbrales de SC-006 y, al ser de la misma serie y sin disco temporal en ambos, el cambio es un *Resize* en
  frío normal (con el servidor vacío se prueba de ida y vuelta, ver más abajo).
- **Por qué se descartaron los demás** (cronología):
  - **D2s_v5** (97,82 USD/mes): la cuota de la familia DSv5 en Chile Central es **0** y la solicitud automática de
    aumento fue **rechazada** (2026-10-16).
  - **D2s_v4** (97,92 USD/mes): aunque la cuota de DSv4 existe, el tamaño figura como
    **`NotAvailableForSubscription`** en el catálogo de la suscripción de estudiante (2026-10-20); no se arregla con
    *Request increase*.
  - **DS2_v2** (2 vCPU, 7 GB, **196,37 USD/mes ≈ 0,2690 USD/h**): el único no burstable de 2 vCPU disponible, pero
    «se retirará pronto» (Microsoft: 05/2028), trae disco temporal local (el cambio con B2ats_v2 es posible en Linux
    según Microsoft Learn, pero añade una incertidumbre sobre `/etc/fstab`) y cuesta 5 veces más por hora: dejarla
    olvidada una semana consumiría 45,19 USD, casi la mitad del crédito, y un mes 196,37 USD, el doble. Se descartó
    por costo, riesgo y obsolescencia, no por imposibilidad técnica.
- **Consecuencia: la CPU es burstable.** Datos de Microsoft Learn (*Basv2 size series*, act. 2026-07; *B-series CPU
  credit model*): B2als_v2 tiene **base 30 %** (por VM), **créditos iniciales 60**, **36 créditos/hora**
  acumulados en reposo y **máximo 864**. Un crédito equivale a 1 vCPU al 100 % durante 1 minuto; la fórmula de
  Microsoft es `((base × vCPU) − (%CPU × vCPU))/100` créditos por minuto, o sea **−1,4 créditos/min** con las
  dos vCPU al 100 %. Sin créditos, la VM **se limita a su base** (30 %). Las métricas «CPU Credits Remaining»,
  «CPU Credits Consumed» y «Percentage CPU» están disponibles en Azure Monitor. Con 200 créditos una carga
  continua al 100 % dura ≈ 143 min; la rampa de ~15 min consume ≈ 21 créditos netos como máximo, de modo que lo
  que importa es no llegar con la reserva casi vacía (humo, siembra y repeticiones también gastan).
- **Procedimiento para que la prueba sea válida (contratos y `docs/despliegue/prueba-de-carga.md`)**:
  1. Antes de cada rampa, «CPU Credits Remaining» **≥ 200** (recomendado ≥ 400). Si hay menos, se deja la VM en
     B2als_v2, **encendida y sin carga** (los créditos no se acumulan con la VM liberada), el tiempo necesario:
     60 → 200 ≈ 4 h; hasta 864 ≈ 22 h (≈ 1,2 USD).
  2. Durante toda la prueba se registra la métrica. Como la VM no tiene credenciales de Azure, no lo hace
     `metrics.sh`: se descarga desde el PC con **`deploy/creditos-cpu.sh`** (`az monitor metrics list`, intervalo
     1 min) y produce `creditos-cpu-*.csv` y `creditos-resumen.json`: créditos al iniciar, mínimo y hora, si se
     agotaron y **en qué minuto de la rampa la CPU quedó limitada**.
  3. Si se agotan, la prueba **se registra igual** con ese minuto (los resultados posteriores se interpretan
     como CPU limitada al 30 %); el criterio solo se declara cumplido con una ejecución que satisfaga los
     umbrales, tenga `evidencia_completa: true` y créditos suficientes al empezar.
- **Perfil `carga` para 4 GB** (antes 8 GB): `FPM_MAX_CHILDREN=20` (START 6, spare 4–10), `MYSQL_BUFFER_POOL=1G`,
  `MYSQL_MAX_CONNECTIONS=100`, `REDIS_MAXMEMORY=256mb`, `PHP_MEMORY_LIMIT=256M`; ≈ 2,6 GB estimados, con el swap de
  2 GB de red de seguridad. El cuello esperado es la CPU (bcrypt), no la memoria; se ajusta con lo observado.
- **Costo**: `plan.md` («Costo estimado»): 47,65 USD (47,7 %) con 4 sesiones de hasta 24 h de acumulación + 4 h de
  prueba; olvidarla una semana cuesta 8,84 USD (6,62 sobre el diario) y un mes 38,40 USD, sin riesgo para el crédito.
- **Cambio B2ats_v2 ↔ B2als_v2**: misma serie, ambas SCSI y sin disco temporal; Microsoft permite además el
  cambio entre tamaños con y sin disco temporal en Linux. Cualquier *Resize* es en frío (Stop → Size → Start). Se
  prueba **una ida y vuelta corta con el servidor vacío** (T071a) para confirmar en la práctica: (a) que B2als_v2
  aparezca en *Size* (`az vm list-vm-resize-options -g rg-sge-staging -n sge-staging --query "[?name=='Standard_B2als_v2']"`);
  (b) que la VM arranque y conserve IP/DNS y SSH; (c) **con cuántos créditos arranca la VM tras el Resize**
  (**medido el 21/10/2026: no se conserva; arranca en ≈ 60** → el cambio se hace la noche anterior); (d) que «CPU Credits
  Remaining» aparezca en *Metrics* para este tamaño. El resultado se anota en `evidencias/vm-azure.md`.
- **Alternativa si el cambio no funcionara**: *Snapshot* del disco → crear disco → VM nueva B2als_v2 con ese disco
  y la misma IP pública estática (y volver igual). No se espera necesitarla.
- **Estado**: CERRADA. T071a verificó (a)–(d) en el paso D (`evidencias/vm-azure.md`); falta solo el regreso a B2ats_v2.

## D-02 Memoria de 1 GB: swap y dimensionado por variables

- **Decisión**: archivo de swap de 2 GB (`/swapfile`, `vm.swappiness=10`) como red de seguridad, no
  como capacidad. Presupuesto en B2ats_v2: SO+Docker ≈ 250 MB, MySQL ≈ 350 MB
  (`innodb_buffer_pool_size=128M`, `performance_schema=OFF`, `max_connections=40`), PHP-FPM ≈ 150 MB
  (`pm.max_children=4`, ~35 MB por worker; OPcache 128 MB compartida), Redis ≈ 40 MB
  (`maxmemory 64mb`), nginx ≈ 10 MB. En B2als_v2 (4 GB): buffer pool 1 GB, `max_children=20`, `max_connections=100`, Redis 256 MB.
  Los valores se pasan por variables (`FPM_*`, `MYSQL_*`, `REDIS_MAXMEMORY`) con un perfil por tamaño
  (ver `contracts/variables-entorno.md`); `docker/php/fpm-pool.conf` sustituye `pm.*` desde el entorno
  con defaults iguales a los actuales para no cambiar el desarrollo local.
- **Justificación**: sin parametrizar, 20 workers (config actual) agotan 1 GB. PHP-FPM expande
  `${VAR}` en el pool; el default se fija con `ENV` en la imagen.
- **Alternativas**: dos archivos de configuración por tamaño (duplicación); sin swap (OOM-kill de
  MySQL al desplegar).
- **Riesgo aceptado**: el disco Standard SSD de 30 GiB tiene pocos IOPS (≈500); el swap activo
  degrada la respuesta. Para demostraciones y la verificación manual es suficiente; la prueba de carga
  corre en B2als_v2.

## D-03 Imágenes: construir en GitHub, publicar en GHCR

- **Decisión**: `release.yml` (push de etiqueta `v*` y `workflow_dispatch` con una etiqueta existente)
  construye con Buildx `linux/amd64` y publica `ghcr.io/jorgeberroccal/sge-app:<etiqueta>` y
  `sge-web:<etiqueta>`.
  `Dockerfile.prod` multi-stage: (1) `node:20-alpine`: `npm ci && npm run build`; (2) `composer:2`:
  `composer install --no-dev --classmap-authoritative --no-scripts`; (3) `php:8.3-fpm` con las mismas
  extensiones que el Dockerfile de desarrollo, código copiado, `vendor` de (2), `public/build` de (1),
  usuario no root, `OPCACHE_VALIDATE_TIMESTAMPS=0`. `nginx/Dockerfile.prod` copia `public/` (con `public/build`) de la imagen `sge-app` de la misma etiqueta (`--build-arg APP_IMAGE`), de modo que los assets servidos coinciden siempre con el manifiesto de Vite del código; por eso `release.yml` construye primero `sge-app` y después `sge-web`.
- **Justificación**: el servidor (1 GB) no puede ejecutar composer/npm; la misma imagen que se probó
  es la que corre; la etiqueta de la imagen es la versión (vuelta atrás = otra etiqueta).
- **Caché de capas**: `type=registry` (tag `cache` de cada paquete en GHCR), **no** `type=gha`. Medido el
  2026-10-16: la caché de Actions queda atada a la referencia que la creó y cada etiqueta es una referencia
  distinta, así que una etiqueta nueva no reutilizaba nada (rc2: 210 s frente a 133 s); con caché en el
  registro, 131 s en frío → 50 s (sge-app 5 s). Ver `evidencias/release.md`.
- **Alternativas**: construir en el servidor (agota RAM); copiar un tar de la imagen por SSH (sin
  historial ni caché); Docker Hub (límites de pull y cuenta extra); registro propio (complejidad).
- **Visibilidad del paquete**: el repositorio `JorgeBerroccal/Sistema-de-Gestion-Escolar` es
  **público** (confirmado por el desarrollador), pero GHCR puede crear el paquete como **privado** en la
  primera publicación. Paso obligatorio tras la primera publicación de cada imagen (`sge-app` y
  `sge-web`): verificar con un pull anónimo (`docker logout ghcr.io; docker pull ghcr.io/<propietario>/sge-app:<etiqueta>`
  desde cualquier equipo) y, si falla con `denied`/`unauthorized`, ir a GitHub → perfil → Packages →
  el paquete → *Package settings* → *Change visibility* → **Public**. Así el servidor no guarda
  credenciales. Las imágenes no contienen secretos (`.env` no se copia: `.dockerignore`).
- **Hallazgo**: la etiqueta `v0.1.0` no tiene `Dockerfile.prod`, por lo que **no se puede publicar su
  imagen**. Las primeras versiones desplegables serán las de este feature (p. ej. `v0.2.0`, y `v0.2.1`
  para probar vuelta atrás).

## D-04 Despliegue y vuelta atrás por SSH

- **Decisión**: el servidor tiene un clon de solo lectura del repositorio en `/opt/sge` (HTTPS si es
  público; si no, deploy key de solo lectura). `deploy/deploy.sh <etiqueta>`: (1) `git fetch --tags` y
  `git checkout <etiqueta>` (así `docker-compose.prod.yml`, `nginx/prod.conf` y los scripts coinciden
  con la versión; el script se re-ejecuta a sí mismo tras el checkout), (2) `docker compose pull` de las
  dos imágenes de esa etiqueta, (3) respaldo previo (`backup.sh pre-<etiqueta>`), (4) levanta `db` y
  `redis`, (5) `run --rm app php artisan migrate --force` con la imagen nueva, (6) `up -d app web`, cuyo
  entrypoint ejecuta `php artisan optimize`, (7) comprobación de salud (`GET /up` por HTTPS, hasta 60 s; el campo `version` debe coincidir con la
  etiqueta, ver `contracts/salud.md`),
  (8) escribe `/opt/sge/VERSION`, `SGE_VERSION` en `.env` y una línea en `/var/log/sge/despliegues.log` (solo si la verificación pasó). Falla → sale con
  error sin borrar la versión anterior; el mensaje indica el comando de vuelta atrás.
- **Migración fallida**: la migración (paso 5) corre **antes** de recrear `app`/`web`; si falla, los
  contenedores anteriores siguen sirviendo y `SGE_VERSION` no cambia. MySQL no revierte el DDL, así que
  la base puede quedar parcialmente modificada: se restaura `pre-<etiqueta>` con `restore.sh` y se
  despliega la etiqueta anterior. Se ensaya en staging con una etiqueta de ensayo (FR-031, DC-012).
- **Vuelta atrás**: `deploy.sh <etiqueta-anterior>`; las migraciones **no** se revierten
  automáticamente (`down` no es fiable). Si la versión fallida aplicó una migración destructiva, se
  ejecuta `restore.sh` con el respaldo `pre-<etiqueta>`. Está documentado como límite (FR-012).
- **Alternativas**: CD automático por SSH desde Actions (el desarrollador pidió que GitHub no
  acceda); `docker compose pull` sin git (el compose no coincide con la versión); Watchtower
  (autoactualiza, sin control).

## D-05 HTTPS con Let's Encrypt en el host

- **Decisión**: certbot (apt) en el host. Emisión inicial con `--standalone` (puerto 80 libre, antes
  del primer `up`), luego `certbot reconfigure --authenticator webroot --webroot-path
  /var/www/certbot` y gancho de despliegue `docker compose exec web nginx -s reload`. nginx monta
  `/etc/letsencrypt` (solo lectura) y `/var/www/certbot`. El temporizador systemd de certbot renueva
  dos veces al día; renueva solo a <30 días. Nombre de host: etiqueta DNS de la IP pública
  (`<nombre>.chilecentral.cloudapp.azure.com`).
- **Justificación**: evita un contenedor extra y el problema huevo-gallina de nginx sin certificado.
- **Alternativas**: contenedor certbot (más piezas); Caddy (otro servidor web, contra el Principio I);
  certificado autofirmado (advertencia del navegador).
- ⚠ El nombre exacto del dominio regional (`chilecentral.cloudapp.azure.com`) se confirma en la
  pantalla de la IP pública.

## D-06 Red y acceso

- **Decisión**: NSG con 3 reglas de entrada (22, 80, 443) y la denegación por defecto de Azure para
  todo lo demás; `ufw` en el host con las mismas tres. SSH: solo clave (`PasswordAuthentication no`,
  `PermitRootLogin no`), usuario `sge`, `fail2ban` (jail `sshd`: 5 fallos → 1 h). Sin filtro por IP de
  origen (IP doméstica dinámica). Docker no publica 3306/6379 (sin `ports:` en `db` y `redis`) y
  `ufw` no filtra puertos publicados por Docker, por eso la garantía es no publicar.
- **Alternativas**: restringir 22 a la IP del desarrollador (descartado: IP dinámica), Azure Bastion
  (costo), puerto SSH distinto (oscuridad, no seguridad).

## D-07 Secretos

- **Mínimo privilegio (FR-032)**: `app` recibe una lista explícita de variables, sin la contraseña root
  de MySQL; respaldo y restauración usan la root dentro del contenedor `db`.
- **Decisión**: `/opt/sge/.env` (0600, propietario `sge`) creado desde `deploy/env.staging.example`
  con `APP_KEY` generada en el servidor (`docker run --rm sge-app php artisan key:generate --show`),
  contraseñas con `openssl rand -base64 24`. Compose lo usa tanto para interpolar como `env_file`.
  `.env` sigue ignorado por Git; `.env.example` agrega las variables nuevas sin valores reales.
  GitHub almacena solo un secreto/variable de la prueba de carga: la URL (variable, no secreto).
- **Verificación de DC-003**: búsqueda en el repositorio y en las imágenes (`docker history`,
  `docker run … env`) de los valores sembrados.

## D-08 Integración continua

- **Decisión**: `ci.yml` en `push` (todas las ramas) y `pull_request`, un job con servicios `mysql:8.0`
  y `redis:7-alpine` (healthchecks), PHP 8.3 con `pdo_mysql, bcmath, intl, zip, redis`, `composer install`
  con caché, `npm ci && npm run build` (las vistas usan `@vite` y fallan sin manifiesto), creación de
  `sge_testing`, `vendor/bin/pest` y `vendor/bin/pint --test`. Pasos separados para que el
  fallo de cada uno se vea. `permissions: contents: read`. Sin secretos; los PR desde bifurcaciones
  corren con el token de solo lectura que GitHub les da.
- ⚠ `phpunit.xml` fija `DB_HOST=db` (nombre de Compose): en el job se define `DB_HOST` real
  pasando `--env`/añadiendo `force`; la tarea de implementación decide entre (a) `extra_hosts`/alias
  `db` apuntando a `127.0.0.1` con `sudo` y (b) cambiar el valor a `${DB_HOST}` con default. Se prefiere (a)
  para no tocar la configuración local.
- ⚠ Se debe comprobar que el repo pasa `pint --test` hoy; si no, un commit de formato previo.
- **Bloqueo de integración**: protección de rama `main` (Settings → Branches → require status checks
  `ci / pruebas` y `ci / estilo`); se documenta, no se automatiza.

## D-09 Prueba de carga desde GitHub

- **Decisión**: `carga.yml` solo `workflow_dispatch`, entradas: `base_url` (por defecto
  `vars.STAGING_URL`), `tamano_vm` (texto libre que se registra, p. ej. `Standard_B2als_v2`),
  `version` (etiqueta desplegada). Pasos: verifica que `base_url` sea igual a `vars.STAGING_URL`
  (única URL permitida → evita apuntar a producción), `GET /up`, ejecuta `grafana/k6` con
  `carga.js` y `--summary-export=resumen.json --out csv` + salida de texto, y sube artefacto
  (`k6-resumen.json`, `k6-salida.txt`, `meta.json` con URL, versión, tamaño declarado, fecha, SHA
  del commit) con retención de 90 días. El runner (ubuntu-latest, 4 vCPU/16 GB) hospeda 1000 VUs
  sin ser el cuello de botella; un paso final imprime el resultado de los umbrales (el job falla si
  k6 sale ≠ 0, pero el artefacto se sube siempre con `if: always()`).
- **Evidencia de servidor**: GitHub no tiene SSH al servidor → `deploy/metrics.sh start` lo lanza el
  desarrollador antes del `Run workflow` y `stop` después; produce `metricas-<fecha>.csv` (cada 5 s:
  CPU %, load, memoria usada, swap, y por contenedor CPU/mem de `docker stats`) y
  `meta-servidor.json` (tamaño leído del servicio de metadatos de la instancia, kernel, versión
  de `/opt/sge/VERSION`). Se descarga con `scp` y se commitea junto al resumen de k6.
- **Siembra**: `LoadTestSeeder` se endurece de "prohibido en producción" a "permitido solo en
  `local`, `staging`, `testing`" (FR-020); en el servidor se ejecuta con `docker compose exec app php
  artisan db:seed --class=LoadTestSeeder --force`.
- **Carga de login**: 1000 logins bcrypt costo 12 ≈ 250 ms CPU cada uno = 250 CPU-s repartidos en la
  rampa; con 2 vCPU el ritmo de login lo escalona la rampa (ver spec 001 R-12). `BCRYPT_ROUNDS=12`
  también en staging, para no falsear la prueba.
- **Alternativas**: k6 Cloud (cuenta y costo); k6 en la propia VM (compite por CPU); cron en el
  servidor (no es "desde GitHub").

## D-10 Cambio de tamaño

- **Decisión**: procedimiento manual desde el portal con VM **liberada**: *Stop* (Deallocate) →
  *Size* → B2als_v2 → *Resize* → *Start*; se mantiene IP estática y DNS (IP Standard estática no cambia);
  disco y datos persisten. Tras arrancar: `docker compose` vuelve solo (`restart: unless-stopped`);
  se cambia `PERFIL_TAMANO=carga` en `.env`, `deploy.sh --reconfigurar` recrea los contenedores con
  los valores completos, y se verifica con `/up`. Vuelta: perfil `diario`, Stop → B2ats_v2 → Start.
  Duración esperada 3–6 minutos cada cambio. El tamaño se confirma con `metrics.sh` (lee IMDS).
- **Costo mientras tanto**: VM liberada no factura cómputo; sí disco e IP (incluido en la estimación).
- Tras el *Start* en B2als_v2 hay que **comprobar los créditos de CPU** y esperar si hacen falta (D-01c).
- ⚠ Si el portal no lista B2als_v2 como destino válido: *Snapshot* del disco → nueva VM B2als_v2
  con ese disco y la misma IP; se documenta como contingencia, no como camino principal.

## D-11 Respaldo y restauración

- **Decisión**: `backup.sh` ejecuta `mysqldump --single-transaction --routines --triggers` dentro del
  contenedor `db`, comprime con gzip y deja `/var/backups/sge/sge-AAAAMMDD-HHMMSS-<etiqueta>.sql.gz`
  (0600); elimina los de más de 7 días; cron diario a las 03:00 hora local (`America/Lima`) y antes de
  cada despliegue. `restore.sh <archivo>`: modo mantenimiento (`artisan down`), detiene `app`/`web`,
  recrea la base y carga el volcado, reinicia, `/up`. Copia fuera del servidor: `scp` periódico
  (documentado; el respaldo en el mismo disco no protege de perder la VM).
- **Alternativas**: snapshots de disco de Azure (costo y granularidad); Percona XtraBackup (innecesario).
- Redis no se respalda (solo sesiones/caché; volátil por diseño).

## D-12 Qué se mide y cómo se cierra SC-006 del Módulo 1 (DC-006)

- **Evidencia completa solo con regreso al tamaño diario**: un registro `/var/log/sge/tamano.log`
  (una línea `fecha-UTC vmSize` al arrancar y cada 5 min, vía cron/IMDS; sobrevive a los cambios de
  tamaño porque está en el disco) demuestra el tamaño real en cada momento. `metrics.sh cierre`
  verifica que, después de `stop`, la última línea sea `Standard_B2ats_v2`, y entonces escribe
  `cierre-carga.json` con `evidencia_completa: true` y las horas en B2als_v2 (incluida la espera de créditos); si no, imprime
  `EVIDENCIA INCOMPLETA: la VM sigue en <tamaño>` y sale ≠ 0. La prueba de carga se considera
  cerrada (y SC-006 del Módulo 1 declarado) solo con `evidencia_completa: true`.
- La rampa completa dura ~15 min; el tiempo restante de las sesiones de 3–4 h es preparación,
  repeticiones y la verificación manual/SC-007 del Módulo 1 hechas con la VM de carga (opcional; FR-026/027 se
  pueden hacer en B2ats_v2). Se hacen ≥3 ejecuciones; la de mejor evidencia que cumpla umbrales
  cierra SC-006 del Módulo 1, y las fallidas se conservan (FR-022).
- **Servidor colgado durante la prueba**: se reinicia desde el portal y se vuelve a B2ats_v2 aunque la
  prueba haya fallado (`contracts/azure-portal.md` §7); la evidencia parcial se conserva (FR-022) y el
  cierre exige igualmente `evidencia_completa: true`.
- Los créditos de CPU se registran aparte (`creditos-cpu.sh`, D-01c): la evidencia dice si llegaron a cero y
  desde qué minuto la CPU quedó limitada.
- Si B2als_v2 (2 vCPU) no alcanza los umbrales por bcrypt, la mitigación ya definida en el Módulo 1 es
  documentar el hallazgo; no se cambia el costo de bcrypt.

## D-13 Verificación manual y SC-007 del Módulo 1 en el servidor

- Se hacen en la VM en B2ats_v2 (no requieren carga), desde un navegador real. Formato de registro:
  `specs/001-auth-roles-rbac/evidencias/staging-verificacion-manual.md` (11 pasos, fecha, versión,
  resultado) y `staging-sc007.md` (≥5 intentos, tiempo, peor caso), análogo a las del Módulo 1.

## D-14 Endurecimiento básico del host

- `unattended-upgrades` para seguridad, `ufw`, `fail2ban`, usuario no root con `sudo`, `docker`
  group solo para `sge`, logs de Docker con rotación (`max-size 10m`, `max-file 3`) para no llenar el disco.

## D-15 Qué hay que tocar en la aplicación

- `docker/php/fpm-pool.conf` parametrizado (defaults iguales); `LoadTestSeeder` con allowlist;
  `.env.example` ampliado; `SESSION_SECURE_COOKIE=true`, `APP_URL=https://…`, `APP_ENV=staging`,
  `APP_DEBUG=false`, `LOG_LEVEL=info`, `LOG_CHANNEL=stderr` en el `.env` del servidor. nginx envía
  `fastcgi_param HTTPS on` para que Laravel genere URLs `https` sin confiar en cabeceras de proxy.
  La ruta `/up` ya existe en Laravel 13 (se confirma en implementación).

## D-16 Documentación

- `docs/despliegue/*.md` en español (FR-030); el README y `quickstart.md` del Módulo 1 reemplazan
  `https://staging.ejemplo.pe` por la URL real y enlazan a `docs/despliegue`.

## D-17 Zona horaria y registros

- Servidor en `America/Lima` para cron y logs; Laravel mantiene `APP_TIMEZONE` actual. Los logs de
  contenedor van a `docker logs` con rotación (D-14).

## D-18 Detener y reactivar

- *Stop (Deallocate)* en el portal cuando no se use; con IP estática la URL no cambia al reiniciar.
  Al arrancar, los contenedores se levantan solos. Con ahorro: B2ats_v2 liberada ≈ 0 USD de cómputo
  (quedan ≈ 0,017 USD/h por disco+IP).
