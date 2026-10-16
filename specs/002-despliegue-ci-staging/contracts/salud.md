# Contrato: comprobación de salud `/up`

Sustituye la ruta de salud predeterminada de Laravel por una propia, sin cambiar ninguna pantalla del
Módulo 1.

- **Ruta**: `GET /up` (pública, sin autenticación, sin sesión, sin Redis ni base de datos: fuera del
  grupo de middleware `web`).
- **Respuesta 200** (`application/json`): `{"status":"ok","version":"<valor de config('app.version')>"}`,
  donde `app.version` sale de la variable `SGE_VERSION` (por defecto `dev`; en staging, la etiqueta
  desplegada, p. ej. `v0.2.0`).
- **Uso**: `deploy.sh` y el workflow `carga.yml` la consultan; `deploy.sh` compara `version` con la
  etiqueta solicitada. El evento `DiagnosingHealth` de Laravel se sigue disparando.
- **Exposición**: solo informa la etiqueta de versión (sin hashes, rutas ni configuración).
- **Pruebas**: `tests/Feature/Salud/SaludTest.php` (200, JSON con `status` y `version`, `version` toma
  `SGE_VERSION`, no crea cookie de sesión, no requiere autenticación).
