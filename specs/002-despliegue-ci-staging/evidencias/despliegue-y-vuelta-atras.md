# Despliegue y vuelta atrás (T058, T062–T065, DC-004)

Horas de Lima (UTC−5). Servidor `sge-staging` en B2ats_v2 (1 GB), `PERFIL_TAMANO=diario`.

## Primer despliegue de v0.2.0 (T058)

Hecho el 21/10/2026 con `./deploy/deploy.sh v0.2.0` como `sge` desde `/opt/sge` (log: `~/deploy-v0.2.0.log` en el servidor).

| Verificación | Resultado |
|---|---|
| Duración | **81 s** informados por `deploy.sh` (DC-004: < 15 min) — incluye la descarga de imágenes de GHCR |
| Migraciones | `create_roles_table`, `create_permisos_table`, `create_rol_permiso_table`, `create_usuarios_table`: todas DONE |
| Contenedores | `db`, `redis`, `app`, `web`: Healthy/Started |
| Comprobación de salud | 17:04:44, `https://sge-peru.chilecentral.cloudapp.azure.com/up` con `version=v0.2.0` |
| Navegador | `/up` → `{"status":"ok","version":"v0.2.0"}`, certificado válido, la raíz muestra el inicio de sesión |

Incidencia previa al despliegue: la comprobación de marcadores `<...>` de `lib.sh` contaba los comentarios de la
plantilla del `.env`; se corrigió en el repositorio (commit `174d11d`) y, como `v0.2.0` conserva el `lib.sh` antiguo,
se borraron del `.env` del servidor esas líneas de comentario antes de desplegar.

Siembra (21/10/2026): `docker compose -p sge -f docker-compose.prod.yml exec app php artisan db:seed --force`.

| Verificación | Resultado |
|---|---|
| `RolesYPermisosSeeder` / `AdministradorInicialSeeder` | DONE (388 ms / 253 ms) |
| Conteos (`tinker`) | roles=5 · permisos=7 · usuarios=1 (sin usuarios demo: `APP_ENV=staging`) |
| Inicio de sesión del Administrador | Correcto; la pantalla de inicio muestra la bienvenida y los accesos a Usuarios, Roles y Permisos |
