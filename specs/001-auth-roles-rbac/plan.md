# Implementation Plan: Autenticación y Roles (RBAC)

**Branch**: `001-auth-roles-rbac` | **Date**: 2026-09-27 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/001-auth-roles-rbac/spec.md`

**Módulo MVP**: 1 — Autenticación y Roles

## Summary

Primer módulo del SGE: inicio/cierre de sesión con correo o nombre de usuario, cuentas activables,
y un RBAC basado en las tablas `roles`, `permisos` y `rol_permiso`, administrable desde la
interfaz. Todo el sistema de autorización de Laravel se conecta a la tabla mediante un único
`Gate::before` que consulta los permisos del rol del usuario en cada solicitud, de modo que el
código solo referencia identificadores de permiso y nunca nombres de rol. Se incluye el punto de
extensión de filtros de pertenencia (interfaz + trait + configuración), fail-closed: un modelo que
declara requerir pertenencia y no tiene regla registrada responde 403. Este módulo también crea el
esqueleto del proyecto Laravel y el entorno Docker que usarán los demás módulos.

## Technical Context

**Language/Version**: PHP 8.3, Laravel 13 (constitución v2.0.0; ver [research.md](research.md#r-14-versión-del-framework-y-de-las-dependencias))

**Primary Dependencies**: Livewire (versión compatible con Laravel 13, R-14), Blade, Tailwind CSS (vía Vite); sin paquetes de
autenticación ni de permisos de terceros

**Storage**: MySQL 8.0 (`sge`, y `sge_testing` para pruebas); sesiones en BD

**Testing**: Pest (versión compatible con Laravel 13, R-14) (+ plugins Laravel y Livewire) para Unit y Feature; k6 para carga del login

**Target Platform**: Contenedores Linux (Docker Compose): php-fpm 8.3 + nginx + MySQL 8

**Project Type**: Aplicación web monolítica renderizada en servidor

**Performance Goals**: Login p95 < 2 s con 100 usuarios concurrentes, errores < 1 % (SC-006)

**Constraints**: Sesión expira a los 120 min de inactividad; 5 intentos fallidos/min por
identificador+IP; plazo del MVP 2 meses

**Scale/Scope**: Una institución por instalación; del orden de cientos a pocos miles de usuarios;
5 roles, 7 permisos base; 6 pantallas (login, inicio, usuarios, formulario de usuario, roles +
permisos de rol, permisos)

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principio | Verificación | Pre-diseño | Post-diseño |
|---|---|:-:|:-:|
| I. Stack fijo | Laravel 13 / PHP 8.3, Blade + Livewire, MySQL 8, Docker Compose. Sin SPA ni otros lenguajes. Tailwind es solo CSS. | ✅ | ✅ |
| II. Diseño genérico | Este módulo no maneja datos de institución/niveles/grados. El Administrador inicial viene del entorno, no del código. | ✅ | ✅ |
| III. RBAC por tablas | Tablas `roles`, `permisos`, `rol_permiso`, `usuarios`. Un único `Gate::before` → `tienePermiso()`; el código usa solo identificadores de permiso. Protección del último admin definida por permisos, no por nombre de rol (R-08). | ✅ | ✅ |
| IV. Alcance fijo | Todo pertenece al módulo 1. Excluidos explícitamente: registro, recuperación de contraseña, 2FA, CRUD de roles. | ✅ | ✅ |
| V. Pruebas obligatorias | Unit + Feature por cada historia; permitido/denegado por cada ruta y acción; k6 para el login (endpoint crítico). | ✅ | ✅ |
| VI. IA restringida | No aplica aún; el mecanismo de pertenencia fail-closed es la base que usará el chatbot (contracts/pertenencia.md). | ✅ N/A | ✅ N/A |
| VII. Simplicidad | Sin starter kits, sin spatie/permission, sin caché de permisos, sin repositorios; login como formulario HTTP clásico. | ✅ | ✅ |
| Restricciones | `.env` + `.env.example`; `docker compose up`; migraciones y seeders reconstruyen la BD con roles, permisos y admin. | ✅ | ✅ |

**Resultado**: sin violaciones. Complexity Tracking vacío.

**Nota**: el plan se ajustó a la constitución v2.0.0 (Laravel 13). Re-verificado el 2026-09-27: los
7 principios siguen cumpliéndose; el cambio de versión no altera ninguna decisión de diseño.

## Project Structure

### Documentation (this feature)

```text
specs/001-auth-roles-rbac/
├── plan.md              # Este archivo
├── research.md          # Fase 0: decisiones R-01…R-14
├── data-model.md        # Fase 1: tablas, estados, regla de cuenta administradora, seeders
├── quickstart.md        # Fase 1: guía de validación
├── contracts/
│   ├── rutas-web.md             # Rutas, permisos, respuestas del login, menú
│   ├── componentes-livewire.md  # Acciones protegidas y validaciones
│   └── pertenencia.md           # Contrato del punto de extensión fail-closed
├── checklists/
│   └── requirements.md
└── tasks.md             # Fase 2 (/speckit-tasks)
```

### Source Code (repository root)

La aplicación Laravel vive en la raíz del repositorio (junto a `.specify/` y `specs/`).

```text
app/
├── Autorizacion/
│   ├── ProteccionAdministracion.php
│   └── Pertenencia/
│       ├── ReglaPertenencia.php
│       ├── RequierePertenencia.php
│       ├── RegistroReglasPertenencia.php
│       └── ReglaPertenenciaFaltante.php
├── Http/
│   ├── Controllers/Auth/LoginController.php
│   ├── Middleware/EnsureUserIsActive.php
│   └── Requests/Auth/LoginRequest.php
├── Livewire/Admin/
│   ├── Usuarios/{Index,Form}.php
│   ├── Roles/{Index,Permisos}.php
│   └── Permisos/Index.php
├── Models/{Usuario,Rol,Permiso}.php
└── Providers/AppServiceProvider.php     # Gate::before + singleton de pertenencia

config/{auth,menu,pertenencia,sge}.php
database/
├── factories/{UsuarioFactory,RolFactory,PermisoFactory}.php
├── migrations/                           # roles, permisos, rol_permiso, usuarios
└── seeders/{DatabaseSeeder,RolesYPermisosSeeder,AdministradorInicialSeeder,
             UsuariosDemoSeeder,LoadTestSeeder}.php
resources/views/
├── layouts/app.blade.php                 # menú desde config/menu.php con @can
├── auth/login.blade.php
├── inicio.blade.php
└── livewire/admin/...
routes/web.php

tests/
├── Unit/
├── Feature/{Auth,Autorizacion,Admin,Seeders,Pertenencia}/
├── Support/Pertenencia/                  # RegistroDePrueba + ReglaDePrueba
└── Load/login.js                         # k6

docker/
├── php/Dockerfile
├── nginx/default.conf
└── mysql/init/01-create-testing-db.sql
docker-compose.yml
.env.example
```

**Structure Decision**: Monolito Laravel estándar en la raíz del repositorio (Principio VII). Las
piezas de autorización propias del SGE se agrupan en `app/Autorizacion/` para que los módulos
futuros las encuentren en un solo lugar. Las vistas de administración son componentes Livewire
bajo `app/Livewire/Admin/`.

## Complexity Tracking

Sin violaciones de la constitución que justificar.
