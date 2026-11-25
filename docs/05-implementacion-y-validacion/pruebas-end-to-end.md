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
- **Imágenes:** a `docker/php/Dockerfile` (desarrollo) solo se le agregó la extensión `sockets`, que exige el servidor HTTP interno del plugin y por tanto `composer.lock`; sin Node ni Chromium. `Dockerfile.prod` no cambia.

## Reproducirlo en el contenedor de desarrollo

Tras reconstruir la imagen (`docker compose build app && docker compose up -d`), `composer install` funciona sin banderas. El contenedor **no trae Node ni Chromium**, así que para ejecutar las pruebas de navegador hay que agregarlos **a mano y de forma temporal** (se pierden al recrearlo):

```bash
docker compose exec -u root app sh -c 'apt-get update && apt-get install -y nodejs'
docker compose exec -u root -e PLAYWRIGHT_BROWSERS_PATH=/opt/pw app node node_modules/playwright/cli.js install --with-deps chromium
docker compose exec -u root -e PLAYWRIGHT_BROWSERS_PATH=/opt/pw app php vendor/bin/pest -c phpunit.e2e.xml
docker compose exec -u root app chown -R sge:sge storage tests/Browser   # el root dejó archivos propios
```

`node_modules/playwright` lo instala `npm install` (está en `devDependencies`). Chromium con sus dependencias del sistema descarga unos 200 MB y tarda varios minutos. No lances a la vez dos ejecuciones de Pest: comparten la base `sge_testing`.

> La imagen de producción no se ve afectada: instala sin dependencias de desarrollo y con `--ignore-platform-reqs`.

## Resultado

- Local (contenedor `app` preparado como arriba, 23/11/2026): **8 pruebas en verde** (15 aserciones, ≈ 55 s).
- CI (24/11/2026, rama `feat/e2e-y-criterios`, PR #23): el job `e2e` salió **en verde** en las dos ejecuciones del mismo commit (`27b7581`), junto con `estilo`, `scripts`, `pruebas` y `cobertura`:

  | Ejecución | Evento | Duración del job `e2e` | Paso «Chromium para Playwright» | Paso «Pest (pruebas End to End)» |
  |---|---|---|---|---|
  | [45320992025](https://github.com/JorgeBerroccal/Sistema-de-Gestion-Escolar/actions/runs/45320992025) | `push` | 1 min 37 s | 25 s | 10 s |
  | [45320997507](https://github.com/JorgeBerroccal/Sistema-de-Gestion-Escolar/actions/runs/45320997507) | `pull_request` | 4 min 59 s | 4 min 9 s | 6 s |

  **Variación observada:** la duración del job difiere en 3 min 22 s entre las dos ejecuciones, y casi toda la diferencia está en el paso de instalación de Chromium; las pruebas en sí tardan 6 a 10 s. Es una sola observación: **si se repite en los próximos CI, se revisará ese paso** (descarga de Chromium y de sus dependencias del sistema en cada ejecución, sin caché). No se ha cambiado nada por ahora.
