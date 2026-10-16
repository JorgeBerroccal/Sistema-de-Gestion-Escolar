# Integración continua (CI)

Cada **push** (a cualquier rama) y cada **pull request** ejecuta el workflow
[`.github/workflows/ci.yml`](../../.github/workflows/ci.yml), llamado `ci`. Muestra dos estados en
GitHub, junto al commit y dentro del pull request:

| Estado | Qué hace |
|---|---|
| `ci / pruebas` | Levanta MySQL 8 y Redis 7 reales como servicios, instala dependencias, compila los assets (las vistas usan `@vite`) y ejecuta la suite **completa** de Pest (`vendor/bin/pest --ci`). |
| `ci / estilo` | Ejecuta Pint en modo solo comprobación (`vendor/bin/pint --test`); no modifica el repositorio. |

El workflow no usa secretos y solo tiene permiso de lectura del repositorio, de modo que los pull
requests desde bifurcaciones no pueden obtener nada del servidor ni de GHCR.

## Leer un fallo

1. En el commit o en el pull request, pulsa **Details** junto al estado rojo.
2. `ci / pruebas`: abre el paso **Pest (suite completa)**; Pest lista la prueba que falló, el archivo y
   la línea, y el mensaje de la aserción.
3. `ci / estilo`: abre el paso **Pint (solo comprobación)**; lista los archivos con problemas y la regla
   (por ejemplo `ordered_imports`).

## Reproducirlo en local

```bash
docker compose up -d
docker compose exec app ./vendor/bin/pest            # suite completa
docker compose exec app ./vendor/bin/pint --test     # comprobar estilo
docker compose exec app ./vendor/bin/pint            # corregir estilo
```

## Exigir que el CI esté verde antes de integrar a `main`

El repositorio no puede imponerlo por sí solo; se configura en GitHub (requiere permisos de
administrador del repositorio):

1. **Settings → Branches → Add branch ruleset** (o *Branch protection rule*) para `main`.
2. Activar **Require a pull request before merging**.
3. Activar **Require status checks to pass** y agregar `ci / pruebas` y `ci / estilo`
   (aparecen en la lista tras la primera ejecución).
4. Guardar. A partir de entonces un PR con el CI en rojo no se puede integrar.

## Notas de diseño

- `phpunit.xml` fija `DB_HOST=db`; el job añade `db` y `redis` a `/etc/hosts` apuntando a `127.0.0.1`,
  donde GitHub publica los contenedores de servicio. Así no se toca la configuración local.
- Una ejecución nueva en la misma rama cancela la anterior en curso (`concurrency`).
