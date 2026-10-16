# Primer despliegue (servidor nuevo)

Requisitos: los recursos de Azure ya creados según [aprovisionamiento-azure.md](aprovisionamiento-azure.md)
(VM Ubuntu 24.04, IP estática con nombre DNS, reglas 22/80/443, clave SSH) y las imágenes de una versión
publicadas (workflow `release`). `<fqdn>` es el nombre DNS de la IP pública.

## 1. Publicar la versión y dejar las imágenes públicas

1. Con el CI en verde en `main`, crear y subir la etiqueta: `git tag v0.2.0 && git push origin v0.2.0`.
2. Esperar al workflow **release** (verde). La primera vez tarda ~7 min; las siguientes reutilizan la
   caché de capas.
3. **Verificar la visibilidad de los paquetes en GHCR** tras la primera publicación. GHCR crea los
   paquetes nuevos como *privados* aunque el repositorio sea público. Desde un equipo sin sesión:

   ```bash
   docker logout ghcr.io
   docker pull ghcr.io/jorgeberroccal/sge-app:v0.2.0
   docker pull ghcr.io/jorgeberroccal/sge-web:v0.2.0
   ```

   Si alguno responde `denied` o `unauthorized`: GitHub → tu perfil → **Packages** → el paquete →
   **Package settings** → **Change visibility** → **Public**. Repetir con el otro paquete y volver a
   probar los dos `docker pull` hasta que funcionen. (El servidor descarga las imágenes sin credenciales.)

## 2. Preparar el servidor (una sola vez)

```bash
ssh -i ~/Descargas/sge-staging-key.pem azureuser@<fqdn>
sudo apt-get update && sudo apt-get install -y git
git clone https://github.com/JorgeBerroccal/Sistema-de-Gestion-Escolar.git ~/sge-src
sudo bash ~/sge-src/deploy/bootstrap-servidor.sh <fqdn> <tu-correo>
```

El script es idempotente y deja: zona horaria `America/Lima`, swap de 2 GB, actualizaciones de
seguridad automáticas, Docker + Compose, usuario `sge` (con tu clave SSH), SSH solo con clave y sin root,
`ufw` (22/80/443), `fail2ban`, el repositorio en `/opt/sge`, el certificado de Let's Encrypt y el cron
de respaldo. Revisa el resumen final (swap activo, ufw activo, `passwordauthentication no`, fecha de
vencimiento del certificado).

## 3. Crear el `.env` del servidor (secretos)

Desde ahora trabaja como `sge`: `ssh -i ~/Descargas/sge-staging-key.pem sge@<fqdn>`.

```bash
cd /opt/sge
cp deploy/env.staging.example .env
chmod 600 .env
# Secretos aleatorios (uno por línea; cópialos al .env):
openssl rand -base64 24    # DB_PASSWORD
openssl rand -base64 24    # DB_ROOT_PASSWORD (distinta)
# APP_KEY, generada con la imagen publicada (el entrypoint exige APP_KEY, por eso --entrypoint php):
docker run --rm --entrypoint php ghcr.io/jorgeberroccal/sge-app:v0.2.0 artisan key:generate --show
nano .env    # reemplazar TODOS los marcadores <...>: APP_KEY, APP_URL, DB_*, SGE_ADMIN_*, REGISTRO, FQDN, SGE_VERSION
```

`SGE_ADMIN_PASSWORD` debe ser fuerte (≥ 16 caracteres). El `.env` nunca se sube al repositorio y solo
existe en el servidor (permisos 600). `deploy.sh` se niega a ejecutarse si quedan marcadores `<...>`.

## 4. Primer despliegue

```bash
cd /opt/sge
./deploy/deploy.sh v0.2.0
docker compose -p sge -f docker-compose.prod.yml exec app php artisan db:seed --force
```

`db:seed --force` ejecuta `DatabaseSeeder`: roles, permisos y el Administrador inicial (los usuarios de
demostración solo se crean en entorno `local`). Comprobar:

```bash
curl -s https://<fqdn>/up              # {"status":"ok","version":"v0.2.0"}
./deploy/deploy.sh --estado
```

Abrir `https://<fqdn>` en un navegador: candado válido y `http://` redirige a `https://`.
