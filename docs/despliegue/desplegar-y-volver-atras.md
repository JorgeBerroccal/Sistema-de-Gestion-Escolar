# Desplegar una versión nueva y volver a la anterior

El servidor **nunca construye imágenes**: GitHub Actions las construye (workflow `release`) y
`deploy.sh` descarga las de la etiqueta indicada. GitHub no tiene acceso al servidor.

## Desplegar una versión

1. Integrar a `main` con el CI en verde.
2. Etiquetar y subir: `git tag v0.2.1 && git push origin v0.2.1`. Esperar a que el workflow **release**
   termine en verde (si es la primera publicación de un paquete nuevo, verificar su visibilidad pública,
   ver [primer-despliegue.md](primer-despliegue.md)).
3. En el servidor, como `sge`:

   ```bash
   cd /opt/sge && ./deploy/deploy.sh v0.2.1
   ```

El script: sincroniza `/opt/sge` con la etiqueta (compose, nginx y scripts coinciden con la versión) →
comprueba que existen las imágenes → hace un **respaldo `pre-<etiqueta>`** → descarga las imágenes →
levanta `db` y `redis` → **aplica las migraciones con la imagen nueva antes de tocar `app`/`web`** →
**sincroniza los datos base con `php artisan sge:sincronizar-base --force`** (permisos, roles y escalas;
idempotente) → recrea `app` y `web` (el arranque ejecuta `php artisan optimize`) → comprueba que `https://<fqdn>/up`
informa la versión pedida → solo entonces registra la versión como activa (`/opt/sge/VERSION`,
`SGE_VERSION` del `.env` y `/var/log/sge/despliegues.log`). Tarda menos de 15 minutos.

Consultar el estado: `./deploy/deploy.sh --estado` (versión activa, versión servida por `/up`, tamaño de
VM, contenedores y días de vigencia del certificado).

## Volver a la versión anterior

Volver atrás es **desplegar la etiqueta anterior**:

```bash
cd /opt/sge && ./deploy/deploy.sh v0.2.0     # menos de 10 minutos
```

`/up` pasa a informar `v0.2.0`. Los datos se conservan porque la base de datos no se recrea.

### Límite: migraciones destructivas

Las migraciones **no se revierten solas**. Si la versión que quitas aplicó una migración que borró o
transformó datos, volver a la imagen anterior no devuelve esos datos: restaura además el respaldo previo
(`/var/backups/sge/sge-…-pre-<etiqueta>.sql.gz`) con `./deploy/restore.sh` y luego despliega la anterior.
Ver [respaldo-y-restauracion.md](respaldo-y-restauracion.md).

## Si el despliegue falla

| Dónde falla | Qué pasa | Qué hacer |
|---|---|---|
| La etiqueta o las imágenes no existen | Error antes de tocar nada | Subir la etiqueta / esperar a `release` / hacer público el paquete |
| **La migración** | Error. `app`/`web` anteriores siguen en marcha y la versión activa no cambia. MySQL no revierte el DDL: la base puede quedar parcialmente modificada | `./deploy/restore.sh <pre-etiqueta>` y luego `./deploy/deploy.sh <anterior>` |
| **La sincronización de datos base** | Error. `app`/`web` anteriores siguen en marcha y la versión activa no cambia. Es idempotente: se puede repetir el despliegue | Corregir la causa (ver el mensaje) y repetir `./deploy/deploy.sh <etiqueta>` |
| `/up` no informa la versión pedida | Error; el script imprime el comando de vuelta atrás | `./deploy/deploy.sh <anterior>`; revisar `docker compose -p sge -f docker-compose.prod.yml logs app web` |

Cada intento queda en `/var/log/sge/despliegues.log` (fecha, etiqueta, commit, tamaño de VM, perfil,
resultado y duración).

## Datos base: permisos, roles y escalas

Cada despliegue ejecuta `sge:sincronizar-base`, que crea los permisos, roles y escalas de calificación que
falten (lista fija de seeders base idempotentes; nunca el Administrador inicial, los usuarios de ejemplo ni
los de carga). Un permiso nuevo se asigna a sus roles por defecto solo la primera vez que se crea, así que no
deshace los cambios que el Administrador haya hecho después. El comando **solo crea**: los permisos que se
eliminen o renombren en una versión posterior se limpian a mano.

## Cambiar el perfil de tamaño sin cambiar de versión

Tras cambiar la VM de tamaño (ver [costo-y-tamano.md](costo-y-tamano.md)), editar `PERFIL_TAMANO` en
`/opt/sge/.env` (`diario` o `carga`) y ejecutar `./deploy/deploy.sh --reconfigurar`.
