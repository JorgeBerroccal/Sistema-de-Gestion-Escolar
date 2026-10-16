# Validación local de la pila de producción (T015)

Fecha: 2026-10-16 · Imágenes `sge-app:local` y `sge-web:local` construidas con `docker/php/Dockerfile.prod` y
`docker/nginx/Dockerfile.prod` (`--build-arg APP_IMAGE=sge-app:local`) · Proyecto Compose `sgeprod`,
`.env` temporal fuera del repositorio, certificado autofirmado para `localhost`.

| Comprobación | Resultado |
|---|---|
| `GET https://localhost:8443/up` | 200 `{"status":"ok","version":"local"}` |
| `http://…` | 301 → `https://localhost/` |
| Inicio de sesión del Administrador por HTTPS y `GET /inicio` | 302 → `/inicio`, luego 200 con menú Usuarios/Roles/Permisos |
| `opcache.validate_timestamps` | `0` |
| `php artisan about` | Environment `staging`, Debug OFF, Config/Events/Routes/Views **CACHED** (por el entrypoint) |
| Usuario del proceso de la app | `uid=1000(sge)` (no root) |
| `.env` dentro de la imagen | ausente (`grep -c` = 0) |
| `env \| grep -ci root` en `app` | 0 (la contraseña root de MySQL no llega a la aplicación, FR-032) |
| Montajes de `app` | 0; `web` solo monta certificados y webroot ACME en solo lectura |
| Puertos publicados | solo 80 y 443 (`web`); `db` 3306/33060 y `redis` 6379 sin publicar |
| Acceso por IP / host desconocido | TLS rechazado (`unrecognized name`) y HTTP cerrado (444) |
| Assets `/build/…` | 200 con `expires` a un año; `server: nginx` sin versión; sin `X-Powered-By` |
| Healthcheck de `web` | `healthy` |
| Migraciones con el usuario de la aplicación (sin root) | correctas |

Hallazgos corregidos durante la validación: (1) `key:generate --show` falla con el entrypoint de la
imagen (exige `APP_KEY`): la documentación usa `--entrypoint php`; (2) el healthcheck por `localhost`
fallaba (IPv6) y por IP lo rechaza el servidor por defecto: se usa `/salud-nginx` en el puerto 80;
(3) `AdministradorInicialSeeder` solo no basta (necesita los roles): la carga inicial en el servidor es
`php artisan db:seed --force`.
