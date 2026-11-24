# Pruebas End to End (navegador real)

Complementan la suite de `tests/Unit` y `tests/Feature`: manejan un Chromium real y recorren la interfaz como lo haría una persona (pantalla de acceso, formularios, Livewire). Usan **Pest Browser** (`pestphp/pest-plugin-browser ^4.3`, la rama compatible con PHP 8.3 y Pest 4; la 5.x exige PHP 8.4 y Pest 5) sobre **Playwright**.

## Flujos cubiertos (`tests/Browser/`)

| Archivo | Flujo | Qué comprueba |
|---|---|---|
| `AccesoTest.php` | Login con usuario o con correo | Ambas formas llegan a `/inicio` sin errores de JavaScript; una contraseña incorrecta se queda en `/login` |
| `RegistroDeNotasTest.php` | Docente registra notas | Con criterios definidos, escribe dos notas en la tabla, pulsa «Guardar notas», ve «Notas guardadas» y las notas quedan en la base |
| `BoletaFamiliaTest.php` | Padre ve la boleta de su hijo | Llega a «Mis notas» y encuentra los enlaces de la boleta del periodo publicado y de la anual, solo los de su hijo |
| `AccesoDenegadoTest.php` | Acceso denegado por rol | Un Docente recibe 403 en la boleta y en `/admin/usuarios`; un Padre, 403 en la boleta de un alumno que no es su hijo |
| `AsistenteTest.php` | Asistente con proveedor falso | El Padre escribe una pregunta, pulsa «Enviar» y ve la respuesta de `ProveedorFalso` (nunca sale a la red) |

Límite conocido: el flujo de la boleta **no descarga el PDF** en el navegador; la descarga (cabeceras, contenido `%PDF`, 403 a ajenos) la cubren las pruebas de `tests/Feature/Evaluacion/Acceso/BoletaAccesoTest.php`.

## Dónde se ejecutan

- **En el CI:** el job `ci / e2e` de [`.github/workflows/ci.yml`](../../.github/workflows/ci.yml) (MySQL y Redis reales, `sockets`, `npx playwright install --with-deps chromium`, `vendor/bin/pest --ci -c phpunit.e2e.xml`). Si falla, sube las capturas como artefacto `e2e-capturas`. Es un job aparte para que `ci / pruebas` no se alargue.
- **No forman parte de `vendor/bin/pest`:** viven en `phpunit.e2e.xml` (suite `Browser`, con `SESSION_DRIVER=file` porque el navegador es un cliente HTTP real y la sesión `array` no sobrevive entre peticiones). `phpunit.xml` solo lista `Unit` y `Feature`.
- **Imagen de desarrollo y de producción:** no se modificaron (`docker/php/Dockerfile` y `Dockerfile.prod`). El contenedor `app` no trae Node, Chromium ni la extensión `sockets`.

## Reproducirlo en el contenedor de desarrollo

Funciona, pero hay que preparar el contenedor **a mano y de forma temporal** (se pierde al recrearlo):

```bash
docker compose up -d app db redis
docker compose exec -u root app sh -c 'docker-php-ext-install sockets && apt-get update && apt-get install -y nodejs'
docker compose exec -u root -e PLAYWRIGHT_BROWSERS_PATH=/opt/pw app node node_modules/playwright/cli.js install --with-deps chromium
docker compose exec -u root -e PLAYWRIGHT_BROWSERS_PATH=/opt/pw app php vendor/bin/pest -c phpunit.e2e.xml
docker compose exec -u root app chown -R sge:sge storage tests/Browser   # el root dejó archivos propios
```

`node_modules/playwright` lo instala `npm install` (está en `devDependencies`). La instalación de Chromium con sus dependencias del sistema descarga unos 200 MB y tarda varios minutos.

> **`composer.lock` ahora exige `ext-sockets`** (lo pide el servidor HTTP interno del plugin). En el contenedor `app`, que no la trae, hay que instalar con `composer install --ignore-platform-req=ext-sockets`. El CI la agrega en `setup-php` de todos los jobs; las imágenes de producción no se ven afectadas porque instalan sin dependencias de desarrollo y con `--ignore-platform-reqs`.

## Resultado

- Local (contenedor `app` preparado como arriba, 23/11/2026): **8 pruebas en verde** (15 aserciones, ≈ 55 s).
- CI: el job `e2e` está definido pero **aún no se ha ejecutado**; el resultado se anota aquí cuando salga.
