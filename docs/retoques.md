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
- **Estado**: absorbido por R-04 (el menú lateral agrupado y colapsable cubre este retoque)

### R-03 · Pregunta de seguimiento sin nombre vuelve a pedir aclaración (H3)
- **Pantalla**: `/asistente` (apoderado con varios hijos)
- **Qué se observa**: tras preguntar por Luna, «¿Y en Matemática?» vuelve a pedir aclaración.
- **Qué se espera**: recordar al último estudiante mencionado dentro de la misma conversación, sin persistir nada. Un nombre en el mensaje manda sobre el recordado, y el alumno recordado se vuelve a validar contra los alumnos propios en cada mensaje (R-13).
- **Prioridad**: Baja (opcional)
- **Estado**: pendiente

### R-04 · Rediseño visual (layout, menú, Inicio y login)
- **Pantalla**: layout general, Inicio, login y, a través de componentes, el resto de pantallas. Absorbe R-02 (menú).
- **Qué se observa**: el menú es una fila de enlaces de texto que se desborda; el Inicio está casi vacío; el login es básico.
- **Qué se espera**: solo con el stack actual (Tailwind 4, Alpine con Livewire, Blade), sin dependencias nuevas.
  1. **Menú lateral** agrupado por secciones (Administración, Gestión académica, Matrícula, Asistencia, Evaluación, Asistente), con íconos y colapsable en pantallas angostas. Grupos e ítems según permisos, sin nombres de rol en el código.
  2. **Inicio con tarjetas** agrupadas (ícono, nombre, descripción corta), generadas desde `config/menu.php` y filtradas por los mismos permisos que el menú, de modo que cada rol vea su propio Inicio. Una prueba verifica que las tarjetas del Inicio coinciden con los ítems del menú para cada rol.
  3. **Login**: botón para mostrar u ocultar la contraseña (Alpine), ícono genérico y nombre de la institución tomado de la base de datos. Sin «Recordarme».
  4. **Encabezado** con nombre del usuario, rol y año escolar vigente.
  5. **Indicadores en el Inicio** solo si son datos reales y respetan permisos (por ejemplo, alumnos matriculados en el año vigente, secciones, docentes). Ningún indicador inventado.
  6. **Íconos**: SVG de Heroicons (licencia MIT) copiados como componentes Blade, con la licencia indicada en el repositorio.
  7. **Componentes Blade reutilizables** (tarjeta, botón, tabla, alerta, etiqueta). Las demás pantallas heredan el estilo a través de ellos; no se rediseñan una por una.
- **Criterio**: sin desplazamiento horizontal a 360 px (relacionado con [M-01](#m-01--usabilidad-en-móvil-ac08--eq-08)).
- **Orden de trabajo**: primero layout, menú, Inicio y login, con capturas en escritorio y en 360 px para revisión del responsable; después el resto de pantallas.
- **Prioridad**: Alta
- **Estado**: fase 1 (layout, menú, Inicio, login y encabezado) implementada el 19/11 en la rama `retoques`, a la espera de la revisión de capturas; la fase 2 (resto de pantallas) sigue pendiente

### R-05 · Cambio de contraseña
- **Pantalla**: opción «Mi cuenta» en el encabezado (se integra al rediseño de R-04), para todo usuario autenticado, y bloqueo de acceso mientras haya un cambio pendiente.
- **Qué se observa**: el usuario no puede cambiar su contraseña; solo el Administrador la restablece, y la contraseña inicial sigue vigente.
- **Qué se espera**:
  1. **Cambio voluntario**: en «Mi cuenta», pedir la contraseña actual, la nueva y su confirmación, con las mismas reglas de longitud del Módulo 1. Al cambiarla, cerrar las demás sesiones del usuario.
  2. **Cambio obligatorio en el primer ingreso**: cuando el Administrador crea una cuenta o restablece su contraseña, el usuario debe definir una nueva antes de acceder a cualquier otra pantalla.
  3. **Datos oficiales**: no se permite editar nombres, apellidos ni documento (están a cargo del Administrador).
  4. **Sin recuperación por correo**: el Administrador restablece la contraseña y se vuelve a exigir el cambio.
  5. **Pruebas**: incluyen que la contraseña actual incorrecta se rechaza y que un usuario con cambio pendiente no puede entrar a otras rutas.
- **Prioridad**: Alta
- **Estado**: pendiente (se implementa desde el 19/11)

### R-06 · Permisos por defecto del Director
- **Pantalla**: menú del Director (Usuarios, Roles y Permisos junto a los catálogos académicos).
- **Qué se observa**: el Director ve en el menú Usuarios, Roles y Permisos; supervisa, no administra accesos.
- **Verificación previa** (hecha el 16/11/2026 sobre `database/seeders/RolesYPermisosSeeder.php`):

  | Permiso del Director hoy | Dónde se decidió |
  |---|---|
  | `usuarios.ver`, `roles.ver` | spec 001, tabla «Permisos base del módulo» (Ver usuarios y Ver roles y permisos: Administrador y Director) |
  | `academico.ver`, `asignaciones.ver_todas` | spec 003, FR-021 y FR-023 (el Director solo consulta todo) |
  | `matricula.ver_todas` | spec 004, FR-024 y la asignación por defecto de los permisos del módulo |
  | `asistencia.ver_todas` | spec 005, research R-06 y FR-012 (el Director solo consulta) |
  | `notas.ver_todas`, `boletas.ver_todas` | spec 006, research R-06 y FR-012 y FR-013 (el Director solo consulta) |

  No hay otras: el Director no tiene permisos de gestión, de «propias» ni `asistente.usar` (spec 007).
- **Solo lectura confirmada** (por lectura de código; no se ejecutó una prueba nueva): en Usuarios, Roles y Permisos el Director solo tiene `usuarios.ver` y `roles.ver`. Las rutas de crear y editar usuarios exigen `usuarios.crear` y `usuarios.editar`; desactivar exige `usuarios.desactivar`; crear o editar permisos exige `permisos.gestionar`; asignar permisos exige `roles.asignar_permisos`. Las vistas ocultan o deshabilitan esos controles y los componentes Livewire repiten `authorize()`. Ninguno de esos permisos se asigna al Director. Ninguna spec exige que el Director vea usuarios: la spec 001 lo incluye en la tabla de permisos base, y lo usa solo como ejemplo en la prueba independiente de la historia 4 (quitarle «ver usuarios»).
- **Propuesta (aprobada por el responsable el 16/11)**: quitar por defecto al Director `usuarios.ver` y `roles.ver` (que abren Usuarios, Roles y Permisos). Mantener `academico.ver`, `asignaciones.ver_todas`, `matricula.ver_todas` (alumnos y nóminas), `asistencia.ver_todas` y `notas.ver_todas`, y también `boletas.ver_todas` (revisar boletas es parte de la supervisión). No se crean permisos nuevos más finos.
- **Al implementar**:
  - Cambiar solo el seeder (`RolesYPermisosSeeder::PERMISOS` y la descripción del rol, hoy «Consulta usuarios y roles.»), que rige las instalaciones nuevas.
  - Ajustar las pruebas que fijan los permisos del Director: `tests/Unit/Seeders/RolesYPermisosDefinicionTest.php` y `tests/Feature/Seeders/RolesYPermisosSeederTest.php` (esta usa `usuarios.ver` del Director como ejemplo de no deshacer cambios del Administrador; cambiar el permiso del ejemplo). Añadir una prueba de los permisos por defecto del Director.
  - Documentar que en instalaciones existentes, como staging, el ajuste lo hace el Administrador desde Roles, porque `sge:sincronizar-base` no quita permisos ya asignados (el seeder solo asigna un permiso a sus roles la primera vez que lo crea).
  - Actualizar las referencias a lo que ve el Director en `docs/`.
  - **Verificación de dependencias**: comprobar que ninguna otra pantalla del Director (asignaciones, nóminas, reportes de asistencia y notas, boletas) depende de `usuarios.ver` o `roles.ver` para mostrar datos, por ejemplo los nombres de los docentes. Hacerlo con el Director sin esos dos permisos, abriendo cada pantalla y con pruebas de que los nombres se muestran. Si alguna depende, avisar al responsable antes de cambiar.
    - Comprobación previa (16/11, por búsqueda en el código): `usuarios.ver` y `roles.ver` solo se usan en las rutas y componentes de `/admin/usuarios`, `/admin/roles` y `/admin/permisos`, y en sus ítems de `config/menu.php`. Ninguna pantalla de asignaciones, matrícula, asistencia, notas o boletas los consulta. Falta la prueba en ejecución.
    - `database/seeders/LoadTestSeeder.php` usa `usuarios.ver` para un rol propio de la prueba de carga, sin relación con el Director.
- **Prioridad**: Media
- **Estado**: implementado el 19/11 en la rama `retoques` (seeder, pruebas y documentación). En instalaciones existentes (local y staging) el ajuste se hace desde Roles, porque `sge:sincronizar-base` no quita permisos ya asignados: el Administrador quita `usuarios.ver` y `roles.ver` al Director.
  - **Evidencia (19/11, responsable)**: en la base local, anterior a R-06, el Director seguía con Usuarios, Roles y Permisos. Se los quitó como Administrador desde Roles, volvió a entrar como `director.demo` y la sección Administración desapareció del Inicio y del menú; el resto de sus pantallas funciona bien.

### R-07 · Identidad del asistente virtual
- **Pantalla**: `/asistente` (cualquier rol con `asistente.usar`). Se implementa junto con R-04.
- **Qué se observa**: el asistente no tiene nombre ni identidad visual; el chat arranca vacío.
- **Qué se espera**:
  1. **Nombre configurable** con la variable de entorno `ASISTENTE_NOMBRE`, con «Yachay» (quechua: saber, aprender) como valor por defecto. Se agrega a `config/asistente.php` y a la lista explícita de variables de `docker-compose.prod.yml`, para que la cubra `tests/Unit/Config/VariablesEnComposeTest.php`.
  2. **Avatar propio y simple**: SVG original, no un personaje conocido, junto a los mensajes del asistente; el encabezado del chat muestra el nombre.
  3. **Saludo inicial** con el nombre y lo que puede responder (notas, asistencia, matrícula y sección).
  4. **Preguntas sugeridas** como botones bajo el saludo; al hacer clic envían la pregunta.
  5. **Indicador «<nombre> está escribiendo…»** mientras llega la respuesta.
  6. **Instrucciones del modelo**: usan el nombre configurado para presentarse.
  7. **Sin cambios**: se mantiene el aviso de que las respuestas las genera una IA, y el asistente no debe presentarse como una persona.
- **Pruebas**: el nombre configurado aparece en el chat y en las instrucciones del modelo, y el valor por defecto es «Yachay».
- **Prioridad**: Media
- **Estado**: implementado el 19/11 en la rama `retoques`

### R-08 · Vistas propias del alumno
- **Pantalla**: «Mi ficha» (`/matricula/mis-alumnos`), «Mi asistencia», «Mis notas» y `/asistente`, con una cuenta con `matricula.ser_alumno` o `matricula.ser_apoderado`. Va con R-04.
- **Qué se observa** (revisado por lectura de código el 19/11/2026):
  - «Mi ficha» usa para el alumno la misma tabla del apoderado, con columna Acciones («Ver ficha e historial»). Sin ficha vinculada muestra «No tienes alumnos vinculados.», redactado para apoderados.
  - «Mi asistencia» y «Mis notas» repiten ese mismo mensaje.
  - El asistente responde «No tienes estudiantes vinculados a tu cuenta, así que no tengo información para consultar.» (`ResolverAlumno`), también en plural.
  - Solo el título de «Mi ficha» depende del permiso; el resto de los textos no.
- **Qué se espera**:
  1. **Textos según el permiso, sin nombres de rol**: con `matricula.ser_alumno` se habla en primera persona de la propia ficha; con `matricula.ser_apoderado`, de los estudiantes a cargo. Si la cuenta tiene ambos permisos, se usa el texto neutro actual («Mis alumnos»).
  2. **«Mi ficha» del alumno** es una tarjeta con sus datos (nombre, documento) y su matrícula vigente, sin tabla ni columna Acciones. El apoderado conserva su lista.
  3. **Sin ficha vinculada**, aviso claro en todas sus pantallas (Mi ficha, Mi asistencia, Mis notas y asistente):
     - Alumno: «Tu cuenta todavía no está vinculada a tu ficha de alumno. Comunícate con la administración del colegio.»
     - Apoderado sin estudiantes: aviso equivalente, con su propio texto (por ejemplo, «Tu cuenta todavía no tiene estudiantes vinculados. Comunícate con la administración del colegio.»).
     - El aviso va en un componente Blade de alerta de R-04, reutilizado por las cuatro pantallas.
  4. **Pruebas** para ambos casos: alumno con y sin ficha, y apoderado con y sin estudiantes, en las cuatro pantallas. Se verifica el texto y que el alumno con ficha no ve la columna Acciones.
- **Prioridad**: Alta
- **Estado**: implementado el 19/11 en la rama `retoques`

## Mediciones pendientes para el congelamiento (21-22/11)

Mediciones de los atributos de calidad que hoy figuran como «No medido» o «Parcial» en [`04`](01-analisis-de-sistema/04-atributos-de-calidad.md) y [`08`](01-analisis-de-sistema/08-escenarios-atributos-calidad.md). Se hacen **después** de implementar los retoques (19-20/11), sobre el código que se congela. Con cada resultado se actualizan los estados de `04` y `08` en un PR pequeño; hasta entonces esos documentos siguen diciendo «No medido» o «Parcial».

### M-01 · Usabilidad en móvil (AC08 / EQ-08)
- **Qué se mide**: (a) que no haya desplazamiento horizontal a 360 px de ancho; (b) cuántas interacciones (clics o toques) lleva llegar a la boleta desde que el padre inicia sesión.
- **Cómo**: modo dispositivo del navegador (DevTools) con ancho de 360 px, en staging o en local. Para (a), recorrer el menú, `/notas/mias` y la boleta, y comprobar que `document.documentElement.scrollWidth` no supera el ancho de la ventana. Para (b), contar las interacciones desde el inicio de sesión hasta la descarga de la boleta, con el Padre de Familia.
- **Cuándo**: después del retoque R-04 (que absorbe R-02, menú), porque cambia la navegación.
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

## Trabajo futuro (no se implementa)

### F-01 · Importación masiva de alumnos, apoderados y cuentas
- **Limitación conocida**: hoy alumnos, apoderados y cuentas se crean uno por uno. La importación masiva desde un archivo quedó **fuera de alcance** en la [spec 003](../specs/003-gestion-academica-base/spec.md) (importación masiva desde Excel) y **no se implementa** antes del congelamiento.
- **Diseño mínimo previsto**, para incluirlo en la documentación durante el congelamiento:
  1. **Carga de archivo** por parte del Administrador.
  2. **Validación** de cada fila antes de crear nada.
  3. **Vista previa con errores**: se muestran las filas válidas y las que fallan, con el motivo de cada error.
  4. **Creación todo o nada**: si alguna fila falla, no se crea ninguna.
  5. **Contraseñas iniciales con cambio obligatorio**, apoyándose en el cambio obligatorio de R-05.
- **Estado**: no implementado (trabajo futuro)
