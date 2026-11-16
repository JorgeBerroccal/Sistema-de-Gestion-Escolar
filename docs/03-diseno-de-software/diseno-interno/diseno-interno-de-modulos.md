# Diseño interno de módulos

> **Proyecto:** SGE · **Backend:** Laravel 13 (PHP 8.3) · **Módulo de referencia:** Evaluación y Boletas
> **Patrón arquitectónico:** MVC con capa de servicios, organizado por dominio (ADR-002)
> **Nivel C4:** 4 – Código

## 1. Propósito

Este documento describe **cómo se organiza el código dentro de un módulo** tal como se construyó. Tiene tres objetivos:

- que se sepa qué archivos forman un módulo y dónde va cada uno;
- que las reglas de negocio queden en los servicios y no en controladores ni vistas;
- que se sepa de qué puede depender cada parte.

Se toma **Evaluación y Boletas** como referencia porque concentra las reglas más importantes (criterios con peso, escala, promedio, cierre de periodo) y la boleta PDF.

## 2. Estructura de carpetas

Los módulos son carpetas de dominio en `app/`; la capa web va en `app/Http/` y `app/Livewire/<Módulo>/`:

```text
app/
├── Evaluacion/
│   ├── EvaluacionService.php         Reglas de escritura: permiso, año vigente, periodo, criterios, notas, cierre
│   ├── CalculoPromedios.php          Clase pura: normaliza valores con la escala, promedia, etiqueta y aprueba
│   ├── ConsultaNotas.php             Lecturas: notas y promedios por periodo, anual y reporte de sección
│   ├── BoletaService.php             Datos de la boleta y PDF con DomPDF
│   └── EvaluacionIncumplida.php      Excepción de dominio (mensajes sin datos personales)
├── Http/Controllers/
│   ├── NotasController.php           Recibe el formulario de la tabla de notas (POST con CSRF)
│   └── BoletaController.php          Autoriza y descarga la boleta
├── Livewire/Evaluacion/
│   ├── Registro/Index.php            Elegir sección y curso
│   ├── Registro/Show.php             Pantalla de notas: periodo, editor de criterios y tabla
│   ├── Cierres/Index.php             Cierre y reapertura de periodos por sección
│   ├── Propia/Index.php              «Mis notas» de la familia y del alumno
│   └── Reportes/Index.php            Reporte de notas por sección y periodo
├── Models/
│   ├── CriterioEvaluacion.php        Criterio con peso (con pertenencia)
│   ├── Nota.php                      Nota por criterio y alumno (con pertenencia)
│   ├── CierrePeriodo.php             Cierre = periodo publicado
│   ├── EscalaCalificacion.php        Escala de la institución (valores, equivalentes, umbral)
│   └── PeriodoEvaluacion.php         Periodos del año escolar
├── Autorizacion/Pertenencia/Reglas/
│   ├── CriterioRegla.php             Qué criterios ve cada usuario
│   └── NotaRegla.php                 Qué notas ve cada usuario
resources/views/
├── livewire/evaluacion/              registro/, cierres/, propia/, reportes/
└── boletas/boleta.blade.php          Plantilla del PDF
database/
├── migrations/                       2026_11_13_000001 a 000004: equivalentes y umbral en la escala, criterios, notas, cierres
└── seeders/                          EvaluacionDemoSeeder, EvaluacionCargaSeeder
tests/
├── Unit/Evaluacion/CalculoPromediosTest.php
├── Feature/Evaluacion/               Reglas, acceso, pertenencia, boleta, cierre, reporte y recorrido
└── Load/evaluacion.js                Sesión de carga k6 del módulo
```

| Archivo | Parte del patrón | Responsabilidad | Puede usar |
|---|---|---|---|
| `Registro/Show.php` | Controlador (Livewire) | Comprobar permiso y pertenencia en `mount()`, editar el esquema de criterios y entregar datos a la vista | `EvaluacionService`, `ConsultaNotas` |
| `NotasController.php` | Controlador | Autorizar, validar el formulario y llamar a `guardarNotas` | `EvaluacionService` |
| `BoletaController.php` | Controlador | Autorizar por permiso, vínculo y publicación, y entregar el PDF | `BoletaService`, `MatriculaRegla::alumnosPropios` |
| `EvaluacionService.php` | Servicio | Verificar quién puede escribir, año vigente y periodo; guardar criterios y notas todo o nada; cerrar y reabrir periodos | Modelos, `CalculoPromedios` |
| `CalculoPromedios.php` | Clase de cálculo | Validar un valor contra la escala y derivar su número; promedios ponderado, simple y anual; letra y aprobación | Nada externo (recibe la escala) |
| `ConsultaNotas.php` | Servicio de lectura | Armar notas y promedios para pantallas, boleta y asistente, respetando la publicación | `EvaluacionService`, modelos |
| `BoletaService.php` | Servicio | Reunir los datos de la boleta (incluido el resumen de asistencia) y generar el PDF | `ConsultaNotas`, `ResumenAsistencia`, `EvaluacionService`, DomPDF |
| `CriterioEvaluacion.php`, `Nota.php` | Modelo | Datos, relaciones y `RequierePertenencia` | Eloquent |
| `CriterioRegla.php`, `NotaRegla.php` | Regla de pertenencia | Filtrar consultas por permiso y relación con los datos | `MatriculaRegla::alumnosPropios` |

## 3. Reglas del módulo

| Parte | Puede hacer | No puede hacer |
|---|---|---|
| Controlador / Livewire | Autorizar, validar formularios, invocar servicios, elegir la vista | Contener las reglas de escala, pesos, cierre o promedio |
| Vista | Mostrar datos | Consultar la base de datos |
| Servicio | Aplicar reglas, usar modelos, lanzar `EvaluacionIncumplida` o 403 | Depender de un controlador o de una vista |
| Clase de cálculo | Calcular con la escala recibida | Acceder a la base de datos o nombrar códigos de escala |
| Modelo | Datos, relaciones y pertenencia | Contener reglas de negocio del módulo |

Estas reglas **no se verifican con pruebas `arch()`** (no existen en `tests/`). Se comprueban con pruebas por módulo: acceso por permiso (`tests/Feature/Evaluacion/Acceso/`), pertenencia (`Pertenencia/`), reglas (`EvaluacionServiceReglasTest`) y ausencia de nombres de rol en el código (`SinCondicionalesDeRolTest`). Las excepciones de dominio no llevan nombres ni documentos: citan posición de fila y columna o un identificador (spec 006, R-17).

## 4. Flujo: guardar las notas de una sección

1. La ruta `POST /notas/registro/{seccion}/{curso}` exige sesión y cuenta activa.
2. `NotasController::guardar` llama a `EvaluacionService::puedeEscribir`: `notas.gestionar`, o `notas.registrar_propia` más el marcador `docencia.dictar` y una asignación en la sección y el curso. Si no, responde 403 antes de tocar datos.
3. Valida el formulario: `periodo` existente y `notas[alumno][criterio]` como cadenas de hasta 20 caracteres.
4. `guardarNotas` repite la comprobación con `exigirEscritura`: año vigente, periodo del año y, para quien no gestiona, periodo abierto.
5. Obtiene los criterios del par (sección, curso, periodo) y la nómina activa (matrículas «matriculado» de la sección).
6. Cada celda se normaliza con `CalculoPromedios::normalizar`, usando la escala de la institución. Una celda vacía significa «sin nota».
7. Si hay algún error (alumno fuera de la nómina, criterio ajeno o valor fuera de la escala), no se guarda nada y se devuelven errores por fila y columna.
8. Si no, una transacción hace `upsert` de las celdas llenas, con `registrado_por`, y borra las vaciadas.
9. El controlador redirige a la pantalla con el resultado. Los promedios no se guardan: `ConsultaNotas` los calcula al leer.

El esquema de criterios se guarda desde `Registro\Show` con `EvaluacionService::guardarEsquema`: también todo o nada, con pesos mayores que 0 que suman exactamente 100 %, y eliminar un criterio borra sus notas.

## 5. Flujo: cerrar un periodo y publicar

1. `Cierres\Index` solo es accesible con `notas.cerrar_periodo`.
2. `EvaluacionService::cerrarPeriodo` comprueba el permiso (`exigirCierre`), el año vigente y que el periodo sea del año, y crea la fila de `cierres_periodo` con `cerrado_por` y `cerrado_en`. Es idempotente: si dos cierres simultáneos chocan, se devuelve el existente.
3. Con la fila creada el Docente ya no puede modificar las notas, y las notas y la boleta quedan publicadas para la familia (`NotaRegla` y `CriterioRegla` incluyen la condición de cierre para `*.ver_propia`).
4. `reabrirPeriodo` elimina la fila y revierte ambos efectos.

## 6. Flujo: descargar la boleta

1. `BoletaController::descargar` exige `boletas.ver_todas` o `boletas.ver_propia`; sin ninguno, 403 (el Docente no tiene boleta).
2. Quien solo tiene `boletas.ver_propia` debe pedir un alumno propio o un hijo (`MatriculaRegla::alumnosPropios`); un alumno ajeno es 403 exista o no.
3. `BoletaService::datos` reúne institución, escala, sección vigente, cursos con notas por criterio, promedio general y resumen de asistencia del rango del periodo (`ResumenAsistencia`). La familia recibe solo periodos publicados; Administrador y Director también borradores, marcados «BORRADOR».
4. `BoletaService::pdf` genera el PDF A4 con DomPDF a partir de `boletas.boleta`, **en la misma solicitud y sin guardarlo en disco** (no hay cola ni worker).
5. El controlador responde con la descarga; el nombre del archivo no lleva datos personales.

## 7. Los demás módulos

Siguen la misma estructura, cada uno en su carpeta de dominio:

| Módulo | Servicios y reglas | Pantallas |
|---|---|---|
| Autenticación y Roles | `LoginController`, `LoginRequest`, `EnsureUserIsActive`, `ProteccionAdministracion`, `Gate::before` | `app/Livewire/Admin/` (usuarios, roles, permisos) |
| Gestión Académica | `AnioEscolarService`, `AsignacionService`, `TipoPeriodo`; los catálogos simples se guardan en los componentes | `app/Livewire/Academico/` |
| Matrícula | `AlumnoService`, `MatriculaService`, `EstadoMatricula`, `Parentesco`, `Sexo` | `app/Livewire/Matricula/` |
| Asistencia | `AsistenciaService`, `ResumenAsistencia` | `app/Livewire/Asistencia/` |
| Asistente Virtual | `AsistenteService`, `ConstructorContexto`, `ResolverAlumno`, `LimiteSolicitudes`, `Disponibilidad`, puerto `ProveedorIA` | `app/Livewire/Asistente/Chat/Index.php` |

No existen módulos de Reportes ni de Auditoría: los reportes están dentro de Asistencia y Evaluación, y no hay bitácora (ADR-010).
