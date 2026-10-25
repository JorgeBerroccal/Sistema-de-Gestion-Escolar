# Quickstart: validar la Gestión Académica Base

**Feature**: `003-gestion-academica-base`

Guía de validación de extremo a extremo; el detalle de reglas está en [data-model.md](data-model.md) y los
contratos en [contracts/](contracts/).

## Prerrequisitos

```bash
docker compose up -d
docker compose exec app php artisan migrate:fresh --seed     # roles, permisos, escalas, Administrador y demos (APP_ENV=local)
docker compose exec app php artisan db:seed --class=AcademicoDemoSeeder
```

Usuarios de demo del Módulo 1 (`director.demo`, `docente.demo`, `padre.demo`, `alumno.demo`, contraseña
`password`) y el Administrador de `SGE_ADMIN_*`.

## 1. Pruebas automáticas

```bash
docker compose exec app php artisan test --filter=Academico
docker compose exec app php artisan test                      # suite completa, incluye SinCondicionalesDeRolTest
```

Esperado: todo en verde; incluye concurrencia de año vigente y prueba de volumen (SC-005).

## 2. Recorrido manual por rol (historias 1–5)

| # | Como | Acción | Resultado esperado |
|---|---|---|---|
| 1 | Administrador | Institución → guardar sin nivel | Rechazado indicando el campo |
| 2 | Administrador | Año 2026 con 4 bimestres; segundo periodo superpuesto | Rechazado con la regla incumplida |
| 3 | Administrador | Marcar vigente 2026 y luego 2027 | Un solo vigente (2027) |
| 4 | Administrador | Grados, secciones A–C, curso con varios grados; curso con grado de otro nivel | Alta correcta; el último rechazado |
| 5 | Administrador | Asignar 3 cursos de 1.° A a un docente en una operación; incluir uno inválido | Sin la inválida: se crean 3; con ella: ninguna y se informa cuál |
| 6 | Administrador | Eliminar curso con asignaciones | Impedido; ofrece desactivar; la asignación sigue visible |
| 7 | Director | Recorrer todas las pantallas; intentar una acción de modificación directa | Ve todo; sin controles; 403 |
| 8 | Docente | «Mis asignaciones»; abrir por URL una asignación de otro docente | Solo las propias (de todos los años); 403 |
| 9 | Padre / Alumno | Abrir cualquier URL `/academico/*` | 403 y sin ítems de menú |
| 10 | Administrador | Quitar `academico.ver` al rol Director desde Roles | El Director pierde el acceso sin cambiar código |

## 3. Despliegue a staging (FR-027, SC-006)

1. Etiquetar `v0.3.0` y ejecutar `deploy.sh`: tras `migrate` corre `sge:sincronizar-base` sin pasos manuales.
2. Con los usuarios de ejemplo de cada rol comprobar que los permisos coinciden con la tabla de
   [permisos-y-pertenencia.md](contracts/permisos-y-pertenencia.md).
3. Repetir el despliegue: no duplica permisos ni revierte cambios hechos por el Administrador.

## 4. Genericidad (SC-007)

Cargar un conjunto distinto (otros niveles, cantidad de grados y secciones) con fábricas o desde la interfaz y
comprobar que todo funciona sin modificar código; `grep` de nombres de nivel/grado/curso en `app/`, `resources/`
y `config/` no devuelve nada.
