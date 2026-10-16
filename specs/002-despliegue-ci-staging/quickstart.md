# Quickstart: validación del despliegue y la integración continua

Guía de verificación de extremo a extremo. Los procedimientos operativos completos se escribirán en
`docs/despliegue/` durante la implementación; aquí solo se prueba que funcionan. `<fqdn>` es el
nombre DNS de la IP pública (contracts/azure-portal.md §3).

## Prerrequisitos

- Recursos de Azure creados según [contracts/azure-portal.md](contracts/azure-portal.md) (§0–§6).
- Variable de repositorio `STAGING_URL=https://<fqdn>` en GitHub; protección de `main` con `ci`.
- Paquetes GHCR visibles para el servidor (público, o PAT `read:packages` en el servidor).

## 1. Integración continua (US2, SC-005)

1. Abrir un PR con un cambio trivial → `ci / pruebas` y `ci / estilo` verdes.
2. Romper una prueba y empujar → `ci / pruebas` rojo con el detalle; revertir.
3. Dejar un archivo mal formateado → `ci / estilo` rojo; corregir con `vendor/bin/pint`.

## 2. Servidor y primer despliegue (US1, US3)

1. `ssh azureuser@<fqdn>` → ejecutar `deploy/bootstrap-servidor.sh <fqdn> <correo>` (ver docs).
2. Crear `/opt/sge/.env` desde `deploy/env.staging.example` (0600).
3. Etiquetar `v0.2.0` y esperar a `release` verde (imágenes en GHCR).
   **Verificar visibilidad (primera publicación)**: desde un equipo sin sesión en GHCR,
   `docker logout ghcr.io; docker pull ghcr.io/jorgeberroccal/sge-app:v0.2.0` y lo mismo con `sge-web`.
   Si responde `denied`, cambiar el paquete a **Public** (perfil → Packages → paquete → *Package
   settings* → *Change visibility*) y repetir el pull hasta que funcione.
4. `./deploy/deploy.sh v0.2.0` → termina con "Desplegada v0.2.0" en < 15 min.
5. Sembrar el Administrador inicial: `docker compose -f docker-compose.prod.yml exec app php artisan db:seed --class=AdministradorInicialSeeder --force`.
6. Navegador externo: `https://<fqdn>` → candado válido; `http://<fqdn>` redirige (SC-001).
7. `nmap -Pn -p 22,80,443,3306,6379 <fqdn>` desde fuera → solo 22, 80, 443 abiertos (SC-002).
8. Servidor: `grep -rE 'APP_KEY=base64|DB_PASSWORD=.+' <repo>` no encuentra nada; `stat -c %a /opt/sge/.env` = 600 (SC-003).
9. `docker compose -f docker-compose.prod.yml exec app php -r 'echo ini_get("opcache.validate_timestamps");'` = `0`; `docker inspect` no muestra montajes de código.
10. `certbot renew --dry-run` pasa (renovación automática).

## 3. Versión nueva y vuelta atrás (US3, SC-004)

1. Etiquetar `v0.2.1` con un cambio visible (p. ej. texto del pie de la página de login) → desplegar.
2. Verificar el cambio; `./deploy/deploy.sh v0.2.0` → el cambio desaparece y los usuarios siguen ahí
   (< 10 min). `./deploy/deploy.sh --estado` muestra la versión activa.

## 4. Respaldo y restauración (US7, SC-009)

1. `./deploy/backup.sh manual` → archivo en `/var/backups/sge`.
2. Crear un usuario de prueba, luego `./deploy/restore.sh <archivo>` → el usuario ya no existe y el
   Administrador inicia sesión (< 15 min). Verificar `crontab`/`cron-sge` y que a los 7 días se purga.

## 5. Verificación manual y SC-007 (US6, SC-008)

Ejecutar los 11 pasos de [../001-auth-roles-rbac/quickstart.md](../001-auth-roles-rbac/quickstart.md#3-verificación-manual)
contra `https://<fqdn>` y registrarlos en `specs/001-auth-roles-rbac/evidencias/staging-verificacion-manual.md`;
medir ≥5 intentos de SC-007 en `staging-sc007.md` (peor caso < 60 s).

## 6. Prueba de carga (US4, SC-006) — sesión de 3–4 h en D2s_v5

1. Cambiar el tamaño (contracts/azure-portal.md §7) y poner `PERFIL_TAMANO=carga`.
2. Sembrar usuarios: `docker compose -f docker-compose.prod.yml exec app php artisan db:seed --class=LoadTestSeeder --force`.
3. Humo primero: workflow `carga` con `humo=true`.
4. `./deploy/metrics.sh start`; lanzar `carga` con `tamano_vm=Standard_D2s_v5`, `version=<etiqueta>`; al
   terminar, `./deploy/metrics.sh stop`.
5. Descargar el artefacto y el CSV (`scp`), copiarlos a `specs/001-auth-roles-rbac/evidencias/` con
   fecha, y resumir umbrales y picos de CPU/memoria. Repetir hasta 3 veces.
6. Seguir la lista de verificación de 14 pasos de [contracts/azure-portal.md §7](contracts/azure-portal.md);
   el último paso es volver a B2ats_v2 **el mismo día** y confirmarlo con `./deploy/metrics.sh cierre`.
   La evidencia solo está completa si `cierre-carga.json` dice `evidencia_completa: true`.

## 7. Costo (US5, SC-007)

Captura de las 3 alertas del presupuesto; *Cost analysis* al 15/11 y al 15/12 comparado con la
estimación del [plan](plan.md#costo-estimado-hasta-el-31122026) (≈45 USD al 31/12).
