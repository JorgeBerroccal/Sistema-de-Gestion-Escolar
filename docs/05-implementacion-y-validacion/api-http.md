# Endpoints HTTP documentados (OpenAPI)

[`openapi.yaml`](openapi.yaml) (OpenAPI 3.0.3) describe **solo los endpoints HTTP reales que no son Livewire**. Es una adaptación del entregable «endpoints REST documentados con OpenAPI/Swagger»: el SGE **no tiene API REST**, porque la interfaz es Blade + Livewire dentro del monolito (RC04, ADR-001). Crear una API solo para el entregable contradiría la arquitectura documentada (ver la [tabla de adaptaciones](README.md#adaptaciones)).

## Qué incluye

Las rutas se tomaron de `routes/web.php` y de `bootstrap/app.php`; los parámetros y las respuestas, de cada controlador.

| Endpoint | Controlador | Para qué |
|---|---|---|
| `GET` y `POST /login` | `Auth\LoginController`, `LoginRequest` | Formulario de inicio de sesión; límite de 5 intentos por login e IP en 60 s |
| `POST /logout` | `Auth\LoginController` | Cierra y reinicia la sesión |
| `GET /up` | `SaludController` | Salud y versión desplegada (JSON, sin sesión) |
| `POST /notas/registro/{seccion}/{curso}` | `NotasController` | Guarda la tabla de notas, todo o nada |
| `GET /boletas/{estudiante}/{periodo}` | `BoletaController` | Descarga la boleta en PDF |
| `GET` y `PUT /mi-cuenta/password` | `CuentaController` | Cambio de contraseña (no estaba en la lista de arranque; es otra ruta real que no es Livewire) |

## Qué no incluye

Las pantallas Livewire (`Route::livewire(...)`: administración, académico, matrícula, asistencia, notas, asistente). Livewire las sirve por su propio mecanismo y no son endpoints de un contrato de API. Tampoco se documentan rutas de `/livewire/*`, que pone el framework.

## Protección

- **Sesión:** las rutas protegidas pasan por `auth`, `auth.session`, `activo` (cierra la sesión de una cuenta inactiva) y `cambio-password` (con un cambio obligatorio pendiente solo deja entrar a «Mi cuenta» y a `logout`). Sin sesión, redirige a `/login`.
- **CSRF:** los `POST` y `PUT` pertenecen al grupo `web`, que lleva la verificación predeterminada de Laravel; `bootstrap/app.php` no la desactiva. Sin `_token` la respuesta es 419.
- **Autorización por recurso:** `NotasController` comprueba `puedeEscribir` antes de tocar datos; `BoletaController` autoriza antes de generar el PDF. La matriz de pruebas está en `tests/Feature/Evaluacion/NotasControllerTest.php` y en `tests/Feature/Evaluacion/Acceso/`.

## Cómo verlo

1. Abrir <https://editor.swagger.io/>.
2. **File → Import file** y elegir `docs/05-implementacion-y-validacion/openapi.yaml`, o pegar su contenido.

También sirve cualquier visor de OpenAPI 3. Los ejemplos de «Try it out» no funcionarán tal cual, porque todas las rutas salvo `/up` y `GET /login` necesitan una sesión y un token CSRF reales.

## Cómo se verificó

El archivo se cargó como YAML válido y se contrastó a mano con las rutas y los controladores citados. **No se validó con una herramienta de OpenAPI** ni se generó desde el código; si una ruta cambia, hay que actualizarlo a mano.
