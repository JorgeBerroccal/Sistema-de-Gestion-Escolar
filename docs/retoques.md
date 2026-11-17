# Retoques pendientes

Lista de ajustes por hacer antes del congelamiento (`v1.0.0`). Se reciben hasta el **miércoles 18/11/2026**, se implementan el **19 y 20** (`v0.7.1`)
y se congela el 21 o el 22. Cada retoque se corrige con pruebas, suite completa y Pint; ninguno exige una sesión de carga (Constitución 2.2.0).

Prioridad: **Alta** (impide usar o expone datos), **Media** (molesta pero hay salida), **Baja** (mejora opcional).

## Plantilla

Copiar al final de «Pendientes» y numerar con el siguiente `R-nn`.

```markdown
### R-nn · Título corto
- **Pantalla**: ruta o nombre de la pantalla (y rol o permiso con el que se ve)
- **Qué se observa**:
- **Qué se espera**:
- **Prioridad**: Alta | Media | Baja
- **Estado**: pendiente
```

## Pendientes

### R-01 · Markdown sin interpretar en las respuestas del asistente (H1)
- **Pantalla**: `/asistente` (cualquier rol con `asistente.usar`)
- **Qué se observa**: las respuestas muestran el Markdown tal cual (asteriscos sueltos, p. ej. `**Tecnología**`).
- **Qué se espera**: texto legible. Renderizarlo de forma segura, escapando el HTML y sin enlaces peligrosos, o pedir al modelo texto sin formato. Con pruebas de que `<script>` y `javascript:` salen escapados.
- **Prioridad**: Media
- **Estado**: pendiente

### R-02 · Menú del Administrador desbordado en ventana angosta (H2)
- **Pantalla**: menú principal (Administrador, más de doce ítems)
- **Qué se observa**: con la ventana angosta el menú se desborda y los ítems quedan blancos sobre fondo claro.
- **Qué se espera**: ítems agrupados en secciones desplegables según los permisos del usuario, adaptable a pantallas pequeñas y sin nombres de rol en el código (SC-003 y SC-005). Revisar las pruebas de menú de los Módulos 1 y 2.
- **Prioridad**: Media
- **Estado**: pendiente

### R-03 · Pregunta de seguimiento sin nombre vuelve a pedir aclaración (H3)
- **Pantalla**: `/asistente` (apoderado con varios hijos)
- **Qué se observa**: tras preguntar por Luna, «¿Y en Matemática?» vuelve a pedir aclaración.
- **Qué se espera**: recordar al último estudiante mencionado dentro de la misma conversación, sin persistir nada. Un nombre en el mensaje manda sobre el recordado, y el alumno recordado se vuelve a validar contra los alumnos propios en cada mensaje (R-13).
- **Prioridad**: Baja (opcional)
- **Estado**: pendiente

## Mediciones pendientes para el congelamiento (21-22/11)

Mediciones de los atributos de calidad que hoy figuran como «No medido» o «Parcial» en [`04`](01-analisis-de-sistema/04-atributos-de-calidad.md) y [`08`](01-analisis-de-sistema/08-escenarios-atributos-calidad.md). Se hacen **después** de implementar los retoques (19-20/11), sobre el código que se congela. Con cada resultado se actualizan los estados de `04` y `08` en un PR pequeño; hasta entonces esos documentos siguen diciendo «No medido» o «Parcial».

### M-01 · Usabilidad en móvil (AC08 / EQ-08)
- **Qué se mide**: (a) que no haya desplazamiento horizontal a 360 px de ancho; (b) cuántas interacciones (clics o toques) lleva llegar a la boleta desde que el padre inicia sesión.
- **Cómo**: modo dispositivo del navegador (DevTools) con ancho de 360 px, en staging o en local. Para (a), recorrer el menú, `/notas/mias` y la boleta, y comprobar que `document.documentElement.scrollWidth` no supera el ancho de la ventana. Para (b), contar las interacciones desde el inicio de sesión hasta la descarga de la boleta, con el Padre de Familia.
- **Cuándo**: después del retoque R-02 (menú), porque cambia la navegación.
- **Meta de diseño**: sin scroll horizontal a 360 px; 3 interacciones hasta la boleta.
- **Estado**: pendiente

### M-02 · Cobertura de la capa de servicios (AC07 / EQ-07)
- **Qué se mide**: porcentaje de cobertura de líneas de las clases de servicio: `app/Academico/`, `app/Matricula/`, `app/Asistencia/`, `app/Evaluacion/`, `app/Asistente/` y `app/Autorizacion/`.
- **Qué hace falta en el contenedor**: un controlador de cobertura. La imagen `docker/php/Dockerfile` (PHP 8.3-fpm) **no trae PCOV ni Xdebug**, y el CI usa `coverage: none`. Lo más simple es PCOV, instalado de forma temporal en el contenedor sin tocar la imagen ni el repositorio (`pecl install pcov`, `docker-php-ext-enable pcov`; se pierde al recrear el contenedor). Alternativa: Xdebug en modo `coverage`.
- **Cómo**: con PCOV activo, `docker compose exec app ./vendor/bin/pest --coverage` (el `<source>` de `phpunit.xml` ya incluye `app/`). Para quedarse con la capa de servicios, tomar del informe las carpetas listadas arriba y calcular el promedio ponderado por líneas; guardar el informe en el PR de actualización de estados.
- **Meta de diseño**: cobertura de servicios ≥ 80 %.
- **Estado**: pendiente

### M-03 · Cambio dentro de su módulo (AC05 / EQ-05)
- **Qué se mide**: cuántos archivos toca un cambio real **fuera** del módulo responsable.
- **Cómo**: sobre uno de los retoques ya integrado (por ejemplo R-01 o R-03, que pertenecen al módulo Asistente), ejecutar `git diff --stat <base>...<rama>` y clasificar cada archivo con la regla de abajo. Registrar el retoque elegido, el rango y el recuento de archivos fuera del módulo.
- **Qué cuenta como «del módulo»** (regla fija para que la medición sea repetible; el ejemplo es el módulo Asistente):
  - carpeta de dominio: `app/Asistente/`
  - componentes Livewire: `app/Livewire/Asistente/`
  - vistas: `resources/views/livewire/asistente/`
  - configuración: `config/asistente.php`
  - pruebas: `tests/Feature/Asistente/`
  - Todo archivo que no esté en esa lista cuenta como **fuera del módulo**, aunque sea un cambio mínimo (rutas, migraciones, traducciones, `docs/`, otros módulos, pruebas de otros módulos). Se informa la lista completa de esos archivos, no solo el número.
- **Meta de diseño**: 0 archivos fuera del módulo responsable. Las pruebas `arch()` de Pest que menciona el diseño no existen; esta medición no las sustituye.
- **Estado**: pendiente

### No realizada: prueba con 5 usuarios (AC08)
La meta de diseño de AC08 incluye que el 90 % de las tareas se completen con 5 usuarios. **No se realizó** y no forma parte de la lista de mediciones anteriores.

Procedimiento previsto, por si se hiciera:
1. Elegir 5 personas que representen al Padre de Familia, sin haber usado el sistema.
2. Darles un celular (o el modo dispositivo a 360 px) y la tarea «encontrar la nota de tu hijo y descargar su boleta», sin ayuda.
3. Registrar por persona si completó la tarea, cuántas interacciones usó y dónde dudó.
4. Reportar el resultado como «x de 5 personas completaron la tarea» y compararlo con la meta de diseño (90 % de las tareas completadas).
